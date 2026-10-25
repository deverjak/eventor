<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Catalog;

use Eventor\Catalog\Application\Event\ConfigurePaymentRules;
use Eventor\Catalog\Application\Event\CreateEvent;
use Eventor\Catalog\Application\Event\CreateEventInput;
use Eventor\Catalog\Application\Event\EventDetailsInput;
use Eventor\Catalog\Application\Event\PaymentRulesInput;
use Eventor\Catalog\Application\Form\EditDraftForm;
use Eventor\Catalog\Application\Program\AddProgramItem;
use Eventor\Catalog\Application\Program\CreateNewsPost;
use Eventor\Catalog\Application\Program\NewsPostInput;
use Eventor\Catalog\Application\Program\ProgramItemInput;
use Eventor\Catalog\Application\Publication\PublishEvent;
use Eventor\Catalog\Application\Registration\ChangeRegistrationState;
use Eventor\Catalog\Application\Structure\ActivityInput;
use Eventor\Catalog\Application\Structure\AddActivity;
use Eventor\Catalog\Application\Structure\AddVariant;
use Eventor\Catalog\Application\Structure\VariantInput;
use Eventor\Catalog\Contract\AnswerScope;
use Eventor\Catalog\Contract\AnswerSetInput;
use Eventor\Catalog\Contract\EventCatalog;
use Eventor\Catalog\Contract\Exception\EventNotFound;
use Eventor\Catalog\Contract\Exception\FormValidationFailed;
use Eventor\Catalog\Contract\Exception\FormVersionNotFound;
use Eventor\Catalog\Contract\Exception\NewsPostNotFound;
use Eventor\Catalog\Contract\Exception\ProgramItemNotFound;
use Eventor\Catalog\Contract\FormSchemaProvider;
use Eventor\Catalog\Contract\FormSelection;
use Eventor\Catalog\Contract\FormSubmission;
use Eventor\Catalog\Contract\FormValidator;
use Eventor\Catalog\Contract\NewsReader;
use Eventor\Catalog\Contract\ProgramReader;
use Eventor\Catalog\Contract\ProgramVisibility;
use Eventor\Catalog\Contract\PublicationState;
use Eventor\Catalog\Contract\RegistrationState;
use Eventor\Shared\Application\Tenant\TenantContext;
use Eventor\Shared\Domain\Capacity\CapacityUnit;
use Eventor\Shared\Domain\Currency;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\FormVersionId;
use Eventor\Shared\Domain\Id\NewsPostId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\VariantId;
use Eventor\Shared\Domain\Money;
use Eventor\Shared\Domain\Payment\PaymentMethod;
use Eventor\Shared\Infrastructure\Doctrine\TenantFilterSynchronizer;
use Eventor\Tests\Support\Catalog\Json;
use Eventor\Tests\Support\IntegrationTestCase;

/**
 * The Contract of Catalog the way Registration, Communication, CheckIn and Content call it: no tenant, explicit
 * organizer, published data only. The data is built with the real commands.
 */
final class CatalogFacadesTest extends IntegrationTestCase
{
    private OrganizerId $organizer;
    private EventId $event;
    private ActivityId $trail;
    private ActivityId $canicross;
    private VariantId $trailVariant;
    private VariantId $relayVariant;
    private FormVersionId $formVersion;

    protected function setUp(): void
    {
        parent::setUp();
        $owner = $this->createOwner('jana@example.test', 'klub', 'Klub Plzeň');
        $this->organizer = OrganizerId::fromString($owner['organizerId']);
        $this->enterTenant();
        $this->buildEvent();
        $this->leaveTenant();
    }

    public function test_resolve_published_slug_finds_published_events_of_the_organizer_only(): void
    {
        $catalog = $this->service(EventCatalog::class);

        self::assertSame($this->event->toString(), $catalog->resolvePublishedSlug($this->organizer, 'vikend')->toString());

        $this->expectException(EventNotFound::class);
        $catalog->resolvePublishedSlug(OrganizerId::generate(), 'vikend');
    }

    public function test_a_draft_cannot_be_resolved_snapshotted_or_summarised(): void
    {
        $catalog = $this->service(EventCatalog::class);
        $this->enterTenant();
        $draft = $this->commandBus()->dispatch(new CreateEvent($this->organizer, new CreateEventInput($this->detailsInput('Koncept'), 'koncept')));
        $this->leaveTenant();
        \assert(\is_array($draft));
        $draftId = EventId::fromString(Json::str($draft, 'eventId'));

        foreach ([
            fn() => $catalog->resolvePublishedSlug($this->organizer, 'koncept'),
            fn() => $catalog->registrationSnapshot($this->organizer, $draftId),
            fn() => $catalog->summary($this->organizer, $draftId),
        ] as $call) {
            try {
                $call();
                self::fail('A draft is invisible to other modules.');
            } catch (EventNotFound) {
                $this->addToAssertionCount(1);
            }
        }
        self::assertSame([], $catalog->summaries($this->organizer, [$draftId]));
    }

    public function test_the_registration_snapshot_carries_everything_registration_needs(): void
    {
        $snapshot = $this->service(EventCatalog::class)->registrationSnapshot($this->organizer, $this->event);

        self::assertSame('vikend', $snapshot->slug);
        self::assertSame('Europe/Prague', $snapshot->timezone);
        self::assertSame(PublicationState::Published, $snapshot->publicationState);
        self::assertSame(RegistrationState::Open, $snapshot->registrationState);
        self::assertSame($this->formVersion->toString(), $snapshot->formVersionId->toString());
        self::assertSame(Currency::CZK, $snapshot->currency);
        self::assertSame([PaymentMethod::Cash], $snapshot->paymentMethods);
        self::assertSame(72, $snapshot->holdTtlHours);
        self::assertSame(14, $snapshot->dueDays);
        self::assertTrue($snapshot->autoRestoreOnLatePayment);
        self::assertTrue($snapshot->selfServiceCancellationAllowed);
        self::assertSame('2026-11-21', $snapshot->ageReferenceDate->format('Y-m-d'), 'the day of the event in its own time zone');
        self::assertSame(1, $snapshot->priceListVersion);
        self::assertTrue($snapshot->acceptsRegistrations(new \DateTimeImmutable('2026-10-26')));

        self::assertCount(2, $snapshot->activities);
        $trail = $snapshot->activity($this->trail);
        self::assertNotNull($trail);
        self::assertSame('Trail 10 km', $trail->name);
        self::assertSame(15, $trail->minAge);
        self::assertTrue($trail->acceptsRegistrations());
        self::assertFalse($snapshot->activity($this->canicross)?->acceptsRegistrations());

        $variant = $snapshot->variant($this->trailVariant);
        self::assertNotNull($variant);
        self::assertTrue($variant->price->equals(Money::of(40000)));
        self::assertSame(CapacityUnit::Seat, $variant->capacityUnit);
        $relay = $snapshot->variant($this->relayVariant);
        self::assertSame(CapacityUnit::Team, $relay?->capacityUnit);
        self::assertSame(4, $relay->teamSize);

        $bucket = $this->dbString("SELECT id FROM registration.capacity_bucket WHERE scope_type = 'variant' AND scope_id = ?", [$this->trailVariant->toString()]);
        self::assertSame($bucket, $variant->capacityBucketId?->toString());
        self::assertSame($this->dbString("SELECT id FROM registration.capacity_bucket WHERE scope_type = 'event' AND scope_id = ?", [$this->event->toString()]), $snapshot->eventBucketId?->toString());
        self::assertSame([], $snapshot->addOns);
    }

    public function test_the_snapshot_reads_the_current_price_list(): void
    {
        $this->enterTenant();
        $this->commandBus()->dispatch(new AddVariant($this->event, $this->trail, new VariantInput('Student', Money::of(20000), null, null, null, 30)));
        $this->leaveTenant();

        $snapshot = $this->service(EventCatalog::class)->registrationSnapshot($this->organizer, $this->event);

        self::assertSame(2, $snapshot->priceListVersion);
        $trail = $snapshot->activity($this->trail);
        self::assertNotNull($trail);
        self::assertCount(2, $trail->variants);
    }

    public function test_summaries_list_published_events_with_venue_and_activities(): void
    {
        $catalog = $this->service(EventCatalog::class);

        $summary = $catalog->summary($this->organizer, $this->event);
        self::assertSame('Borský podzimní víkend 2026', $summary->name);
        self::assertSame('Borský park', $summary->venue?->name);
        self::assertSame('https://mapy.cz/borsky-park', $summary->venue->mapUrl);
        self::assertSame(['Trail 10 km', 'Canicross'], array_map(static fn($a): string => $a->name, $summary->activities));
        self::assertSame(RegistrationState::Open, $summary->registrationState);

        $summaries = $catalog->summaries($this->organizer, [$this->event, EventId::generate()]);
        self::assertSame([$this->event->toString()], array_keys($summaries));
        self::assertSame([], $catalog->summaries(OrganizerId::generate(), [$this->event]), 'another organizer sees nothing');
        self::assertSame([], $catalog->summaries($this->organizer, []));
    }

    public function test_the_form_schema_provider_serves_published_versions_only(): void
    {
        $provider = $this->service(FormSchemaProvider::class);

        self::assertSame($this->formVersion->toString(), $provider->currentVersionId($this->organizer, $this->event)->toString());
        $view = $provider->schema($this->organizer, $this->formVersion);
        self::assertSame(1, $view->version);
        self::assertSame($this->event->toString(), $view->eventId->toString());
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $view->schemaSha256);
        $dog = array_values(array_filter($view->fields, static fn($f): bool => 'dog_name' === $f->key))[0];
        self::assertSame(AnswerScope::Person, $dog->scope);
        self::assertTrue($dog->required);
        self::assertSame([$this->canicross->toString()], array_map(static fn($id): string => $id->toString(), $dog->activityIds));

        // A draft of the next version is not served, neither is a version of another organizer.
        $this->enterTenant();
        $this->commandBus()->dispatch(new EditDraftForm($this->event, ['fields' => []]));
        $this->leaveTenant();
        self::assertSame($this->formVersion->toString(), $provider->currentVersionId($this->organizer, $this->event)->toString());
        $draftId = FormVersionId::fromString($this->dbString('SELECT id FROM catalog.form_version WHERE event_id = ? AND published_at IS NULL', [$this->event->toString()]));
        try {
            $provider->schema($this->organizer, $draftId);
            self::fail('Drafts are invisible.');
        } catch (FormVersionNotFound) {
            $this->addToAssertionCount(1);
        }
        $this->expectException(FormVersionNotFound::class);
        $provider->schema(OrganizerId::generate(), $this->formVersion);
    }

    public function test_the_form_validator_validates_against_the_published_version(): void
    {
        $validator = $this->service(FormValidator::class);
        $onTrail = new FormSelection([$this->trail], [$this->trailVariant]);

        $result = $validator->validate($this->organizer, $this->formVersion, new FormSubmission($onTrail, [
            new AnswerSetInput(AnswerScope::Order, 'order', ['orderer_first_name' => 'Jana', 'orderer_last_name' => 'Nováková', 'orderer_email' => 'Jana@Example.test', 'orderer_phone' => '777 123 456']),
            new AnswerSetInput(AnswerScope::Person, 'persons[0]', ['first_name' => 'Jana', 'last_name' => 'Nováková', 'birth_date' => '1990-05-04']),
        ]));

        self::assertCount(2, $result->answerSets);
        self::assertSame('jana@example.test', $result->answerSets[0]->semantic->email);
        self::assertSame('+420777123456', $result->answerSets[0]->semantic->phone);
        self::assertSame('1990-05-04', $result->answerSets[1]->semantic->birthDate?->format('Y-m-d'));
        self::assertSame('persons[0]', $result->answerSets[1]->subjectRef);

        try {
            $validator->validate($this->organizer, $this->formVersion, new FormSubmission(new FormSelection([$this->canicross], []), [
                new AnswerSetInput(AnswerScope::Person, 'persons[1]', ['first_name' => 'Petr', 'last_name' => '', 'birth_date' => 'včera']),
            ]));
            self::fail('The answers are invalid.');
        } catch (FormValidationFailed $e) {
            $codes = [];
            foreach ($e->violations() as $v) {
                $codes[$v['path']] = $v['code'];
            }
            self::assertSame(['persons[1].last_name' => 'required', 'persons[1].birth_date' => 'date', 'persons[1].dog_name' => 'required'], $codes);
        }

        $this->expectException(FormVersionNotFound::class);
        $validator->validate(OrganizerId::generate(), $this->formVersion, new FormSubmission($onTrail, []));
    }

    public function test_missing_required_fields_after_a_new_version(): void
    {
        $validator = $this->service(FormValidator::class);

        $missing = $validator->missingRequiredFields($this->organizer, $this->formVersion, AnswerScope::Person, new FormSelection([$this->canicross], []), ['first_name', 'last_name', 'birth_date']);
        self::assertSame(['dog_name'], $missing);
        self::assertSame([], $validator->missingRequiredFields($this->organizer, $this->formVersion, AnswerScope::Person, new FormSelection([$this->trail], []), ['first_name', 'last_name', 'birth_date']));
    }

    public function test_the_program_reader_filters_by_audience(): void
    {
        $reader = $this->service(ProgramReader::class);

        $public = $reader->forEvent($this->organizer, $this->event, ProgramVisibility::Public);
        $participants = $reader->forEvent($this->organizer, $this->event, ProgramVisibility::Participants);

        self::assertSame(['Rozcvička'], array_map(static fn($i): string => $i->title, $public));
        self::assertSame(['Porada', 'Rozcvička'], array_map(static fn($i): string => $i->title, $participants));
        $item = $reader->item($this->organizer, $public[0]->id);
        self::assertSame($this->trail->toString(), $item->activityId?->toString());
        self::assertSame('Louka', $item->location);

        $this->expectException(ProgramItemNotFound::class);
        $reader->item(OrganizerId::generate(), $public[0]->id);
    }

    public function test_the_program_of_an_unpublished_or_foreign_event_is_not_found(): void
    {
        $this->expectException(EventNotFound::class);
        $this->service(ProgramReader::class)->forEvent(OrganizerId::generate(), $this->event, ProgramVisibility::Public);
    }

    public function test_the_news_reader_serves_published_posts_only(): void
    {
        $reader = $this->service(NewsReader::class);
        $published = NewsPostId::fromString($this->dbString("SELECT id FROM catalog.news_post WHERE title = 'Vítejte'"));
        $draft = NewsPostId::fromString($this->dbString("SELECT id FROM catalog.news_post WHERE title = 'Koncept'"));

        $view = $reader->post($this->organizer, $published);
        self::assertSame('Vítejte', $view->title);
        self::assertSame($this->event->toString(), $view->eventId->toString());

        foreach ([[$this->organizer, $draft], [OrganizerId::generate(), $published]] as [$organizer, $id]) {
            try {
                $reader->post($organizer, $id);
                self::fail('Not served.');
            } catch (NewsPostNotFound) {
                $this->addToAssertionCount(1);
            }
        }
    }

    private function buildEvent(): void
    {
        $bus = $this->commandBus();
        $created = $bus->dispatch(new CreateEvent($this->organizer, new CreateEventInput($this->detailsInput('Borský podzimní víkend 2026'), 'vikend', 'multi')));
        \assert(\is_array($created));
        $this->event = EventId::fromString(Json::str($created, 'eventId'));

        $trail = $bus->dispatch(new AddActivity($this->event, new ActivityInput('Trail 10 km', minAge: 15, startsAt: new \DateTimeImmutable('2026-11-21T11:00:00+01:00'), capacityLimit: 150)));
        $canicross = $bus->dispatch(new AddActivity($this->event, new ActivityInput('Canicross', startsAt: new \DateTimeImmutable('2026-11-22T10:00:00+01:00'))));
        \assert(\is_array($trail) && \is_array($canicross));
        $this->trail = ActivityId::fromString(Json::str($trail, 'activityId'));
        $this->canicross = ActivityId::fromString(Json::str($canicross, 'activityId'));
        $variant = $bus->dispatch(new AddVariant($this->event, $this->trail, new VariantInput('Jednotlivec', Money::of(40000), null, null, null, 150)));
        $relay = $bus->dispatch(new AddVariant($this->event, $this->canicross, new VariantInput('Tým 4 os.', Money::of(120000), 4, null, null, 20)));
        \assert(\is_array($variant) && \is_array($relay));
        $this->trailVariant = VariantId::fromString(Json::str($variant, 'variantId'));
        $this->relayVariant = VariantId::fromString(Json::str($relay, 'variantId'));

        $bus->dispatch(new ConfigurePaymentRules($this->event, new PaymentRulesInput(['cash'], 14, 72, true)));
        $fields = [
            ['key' => 'first_name', 'type' => 'short_text', 'label' => 'Jméno', 'scope' => 'person', 'required' => true, 'semantic' => 'first_name'],
            ['key' => 'last_name', 'type' => 'short_text', 'label' => 'Příjmení', 'scope' => 'person', 'required' => true, 'semantic' => 'last_name'],
            ['key' => 'birth_date', 'type' => 'date', 'label' => 'Narození', 'scope' => 'person', 'required' => true, 'semantic' => 'birth_date'],
            ['key' => 'dog_name', 'type' => 'short_text', 'label' => 'Jméno psa', 'scope' => 'person', 'required' => true, 'activityIds' => [$this->canicross->toString()]],
            ['key' => 'orderer_first_name', 'type' => 'short_text', 'label' => 'Jméno objednávajícího', 'scope' => 'order', 'required' => true, 'semantic' => 'first_name'],
            ['key' => 'orderer_last_name', 'type' => 'short_text', 'label' => 'Příjmení objednávajícího', 'scope' => 'order', 'required' => true, 'semantic' => 'last_name'],
            ['key' => 'orderer_email', 'type' => 'email', 'label' => 'E-mail', 'scope' => 'order', 'required' => true, 'semantic' => 'email'],
            ['key' => 'orderer_phone', 'type' => 'phone', 'label' => 'Telefon', 'scope' => 'order', 'semantic' => 'phone'],
        ];
        $bus->dispatch(new EditDraftForm($this->event, ['fields' => $fields]));

        $bus->dispatch(new AddProgramItem($this->event, new ProgramItemInput('Rozcvička', new \DateTimeImmutable('2026-11-21T07:30:00+01:00'), location: 'Louka', activityId: $this->trail->toString())));
        $bus->dispatch(new AddProgramItem($this->event, new ProgramItemInput('Porada', new \DateTimeImmutable('2026-11-21T07:00:00+01:00'), visibility: 'participants')));
        $bus->dispatch(new CreateNewsPost($this->event, new NewsPostInput('Vítejte', 'Těšíme se.', publish: true)));
        $bus->dispatch(new CreateNewsPost($this->event, new NewsPostInput('Koncept', 'Ještě ne.')));

        $published = $bus->dispatch(new PublishEvent($this->event));
        \assert(\is_array($published));
        $this->formVersion = FormVersionId::fromString(Json::str($published, 'formVersionId'));
        $bus->dispatch(new ChangeRegistrationState($this->event, null, 'open'));
        $bus->dispatch(new ChangeRegistrationState($this->event, $this->trail, 'open'));
    }

    private function detailsInput(string $name): EventDetailsInput
    {
        return new EventDetailsInput(
            $name,
            new \DateTimeImmutable('2026-11-21T08:00:00+01:00'),
            new \DateTimeImmutable('2026-11-22T16:00:00+01:00'),
            venues: [new \Eventor\Catalog\Application\Event\VenueInput('Borský park', 'Plzeň', 'https://mapy.cz/borsky-park')],
        );
    }

    private function enterTenant(): void
    {
        $this->service(TenantContext::class)->enter($this->organizer);
        $this->service(TenantFilterSynchronizer::class)->sync();
    }

    private function leaveTenant(): void
    {
        $this->service(TenantContext::class)->leave();
        $this->service(TenantFilterSynchronizer::class)->sync();
    }
}
