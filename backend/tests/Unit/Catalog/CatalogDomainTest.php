<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Catalog;

use Eventor\Catalog\Contract\ProgramVisibility;
use Eventor\Catalog\Domain\Event\CapacityLimitChanged;
use Eventor\Catalog\Domain\Event\EventDetailsUpdated;
use Eventor\Catalog\Domain\Event\FormVersionFrozen;
use Eventor\Catalog\Domain\Event\PriceListChanged;
use Eventor\Catalog\Domain\Exception\FormVersionImmutable;
use Eventor\Catalog\Domain\Exception\InvalidCatalogData;
use Eventor\Catalog\Domain\Exception\NewsPostAlreadyPublished;
use Eventor\Catalog\Domain\Exception\VersionConflict;
use Eventor\Catalog\Domain\Form\DefaultFormSchema;
use Eventor\Catalog\Domain\Form\FormSchema;
use Eventor\Catalog\Domain\Form\FormVersion;
use Eventor\Catalog\Domain\Program\NewsPost;
use Eventor\Catalog\Domain\Program\ProgramItem;
use Eventor\Catalog\Domain\Program\ProgramItemDetails;
use Eventor\Catalog\Domain\Structure\ActivityDetails;
use Eventor\Catalog\Domain\Structure\EventDetails;
use Eventor\Catalog\Domain\Structure\EventVisibility;
use Eventor\Catalog\Domain\Structure\PaymentRules;
use Eventor\Catalog\Domain\Structure\VariantDetails;
use Eventor\Shared\Domain\Capacity\CapacityScope;
use Eventor\Shared\Domain\Capacity\CapacityUnit;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\FormVersionId;
use Eventor\Shared\Domain\Id\NewsPostId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ProgramItemId;
use Eventor\Shared\Domain\Money;
use Eventor\Shared\Domain\Payment\PaymentMethod;
use Eventor\Tests\Support\Catalog\Build;
use PHPUnit\Framework\TestCase;

/**
 * Domain rules of the catalogue without a kernel: value objects, optimistic lock, form versions, news.
 */
final class CatalogDomainTest extends TestCase
{
    public function test_the_event_period_and_the_registration_window_must_make_sense(): void
    {
        $base = Build::details();

        $this->expectException(InvalidCatalogData::class);
        new EventDetails($base->name, null, null, null, null, null, null, 'Europe/Prague', $base->endsAt, $base->startsAt, null, EventVisibility::Public, [], null, null);
    }

    public function test_an_unknown_time_zone_and_an_inverted_registration_window_are_refused(): void
    {
        $base = Build::details();
        $make = static fn(string $tz, ?\DateTimeImmutable $opens, ?\DateTimeImmutable $closes): EventDetails => new EventDetails($base->name, null, null, null, null, null, null, $tz, $base->startsAt, $base->endsAt, null, EventVisibility::Public, [], $opens, $closes);

        foreach ([
            static fn() => $make('Mars/Olympus', null, null),
            static fn() => $make('Europe/Prague', $base->endsAt, $base->startsAt),
        ] as $attempt) {
            try {
                $attempt();
                self::fail('Invalid details.');
            } catch (InvalidCatalogData) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_changing_details_records_the_changed_fields_and_whether_the_event_is_live(): void
    {
        $event = Build::event();
        $event->releaseEvents();
        $new = new EventDetails('Nový název', 'Víkend v Borském parku', null, null, null, null, null, 'Europe/Prague', $event->startsAt(), $event->endsAt(), null, EventVisibility::Unlisted, Build::details()->venues, null, null);

        $event->changeDetails($new, Build::now());

        $recorded = $event->releaseEvents();
        self::assertCount(1, $recorded);
        self::assertInstanceOf(EventDetailsUpdated::class, $recorded[0]);
        self::assertSame(['name', 'visibility'], $recorded[0]->changedFields);
        self::assertFalse($recorded[0]->live);
        self::assertSame('Nový název', $event->name());
    }

    public function test_an_unchanged_update_is_still_recorded_with_no_fields(): void
    {
        $event = Build::event();
        $event->releaseEvents();

        $event->changeDetails($event->details(), Build::now());

        $recorded = $event->releaseEvents();
        self::assertInstanceOf(EventDetailsUpdated::class, $recorded[0]);
        self::assertSame([], $recorded[0]->changedFields);
    }

    public function test_payment_rules_survive_the_json_round_trip(): void
    {
        $rules = new PaymentRules([PaymentMethod::BankTransfer, PaymentMethod::Cash], 14, 72, true, false, new \DateTimeImmutable('2026-11-01 00:00:00+00:00'));

        $again = PaymentRules::fromArray(json_decode(json_encode($rules->toArray(), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR));

        self::assertEquals($rules, $again);
        self::assertTrue($again->allows(PaymentMethod::Cash));
        self::assertFalse(PaymentRules::defaults()->allows(PaymentMethod::Cash));
    }

    public function test_the_free_method_is_never_a_configured_method(): void
    {
        $rules = PaymentRules::fromArray(['methods' => ['free', 'cash', 'bitcoin', 'cash']]);

        self::assertSame([PaymentMethod::Cash], $rules->methods);
    }

    public function test_a_variant_is_a_team_variant_when_it_has_more_than_one_person(): void
    {
        $event = Build::event();
        $activity = Build::activity($event, 'Štafeta');

        self::assertSame(CapacityUnit::Seat, Build::variant($event, $activity)->capacityUnit());
        self::assertSame(CapacityUnit::Seat, Build::variant($event, $activity, teamSize: 1)->capacityUnit());
        self::assertSame(CapacityUnit::Team, Build::variant($event, $activity, 120000, 4, 20)->capacityUnit());
    }

    public function test_a_variant_price_is_money_and_cannot_be_negative(): void
    {
        $event = Build::event();
        $variant = Build::variant($event, Build::activity($event), 40000);

        self::assertTrue($variant->price()->equals(Money::of(40000)));
        $this->expectException(InvalidCatalogData::class);
        new VariantDetails('Záporná', Money::of(-1), null, null, null);
    }

    public function test_changing_a_variant_reports_the_changed_fields(): void
    {
        $event = Build::event();
        $variant = Build::variant($event, Build::activity($event), 30000);

        $changed = $variant->change(new VariantDetails('Jednotlivec', Money::of(35000), null, 15, null));

        self::assertSame(['price', 'min_age'], $changed);
    }

    public function test_ages_must_be_ordered(): void
    {
        $this->expectException(InvalidCatalogData::class);
        new ActivityDetails('Závod', null, null, null, null, null, null, 18, 15, false);
    }

    public function test_price_list_version_and_capacity_changes_record_whether_the_event_is_live(): void
    {
        $event = Build::event();
        $event->releaseEvents();

        $event->bumpPriceListVersion(Build::now());
        $event->changeCapacityLimit(150, false, null, Build::now());

        [$price, $capacity] = $event->releaseEvents();
        self::assertInstanceOf(PriceListChanged::class, $price);
        self::assertSame(2, $price->version);
        self::assertInstanceOf(CapacityLimitChanged::class, $capacity);
        self::assertSame(CapacityScope::Event, $capacity->scope);
        self::assertSame([null, 150], [$capacity->previousLimit, $capacity->newLimit]);
        self::assertSame($event->capacityBucketId(), $capacity->bucketId);
    }

    public function test_the_optimistic_lock_compares_versions(): void
    {
        $event = Build::event();

        $event->assertVersion(null);
        $event->assertVersion($event->version());
        $this->expectException(VersionConflict::class);
        $event->assertVersion($event->version() + 1);
    }

    public function test_a_published_form_version_is_frozen_with_a_hash(): void
    {
        $organizer = OrganizerId::generate();
        $event = EventId::generate();
        $draft = FormVersion::startDraft(FormVersionId::generate(), $organizer, $event, 1, DefaultFormSchema::create(), null, Build::now());
        $draft->releaseEvents();

        $draft->publish(null, Build::now());

        self::assertTrue($draft->isPublished());
        self::assertSame(DefaultFormSchema::create()->sha256(), $draft->schemaSha256());
        [$frozen] = $draft->releaseEvents();
        self::assertInstanceOf(FormVersionFrozen::class, $frozen);
        self::assertSame([], $frozen->addedRequiredFieldKeys, 'nobody has answered the first version yet');

        $this->expectException(FormVersionImmutable::class);
        $draft->replaceSchema(FormSchema::empty(), Build::now());
    }

    public function test_the_next_version_lists_the_required_fields_it_adds_s17(): void
    {
        $organizer = OrganizerId::generate();
        $event = EventId::generate();
        $v1 = FormVersion::startDraft(FormVersionId::generate(), $organizer, $event, 1, DefaultFormSchema::create(), null, Build::now());
        $v1->publish(null, Build::now());

        $fields = DefaultFormSchema::create()->toArray()['fields'];
        $fields[] = ['key' => 'dog_name', 'type' => 'short_text', 'label' => 'Jméno psa', 'scope' => 'person', 'required' => true];
        $fields[] = ['key' => 'note', 'type' => 'long_text', 'label' => 'Poznámka', 'scope' => 'person', 'required' => false];
        $v2 = FormVersion::startDraft(FormVersionId::generate(), $organizer, $event, 2, FormSchema::fromArray(['fields' => $fields]), null, Build::now());
        $v2->releaseEvents();

        $v2->publish($v1, Build::now());

        [$frozen] = $v2->releaseEvents();
        self::assertInstanceOf(FormVersionFrozen::class, $frozen);
        self::assertSame(['dog_name'], $frozen->addedRequiredFieldKeys);
        self::assertSame($v1->id(), $frozen->previousFormVersionId);
        self::assertFalse($v1->schema()->field('dog_name') instanceof \Eventor\Catalog\Domain\Form\FieldDefinition, 'version 1 keeps its schema');
    }

    public function test_a_news_post_is_edited_as_a_draft_and_final_once_published(): void
    {
        $post = NewsPost::draft(NewsPostId::generate(), OrganizerId::generate(), EventId::generate(), null, 'Start', 'Startujeme v 10:00', 0, false, Build::now());

        $post->edit('Start posunut', 'Startujeme v 11:00', 5, true, Build::now());
        $post->publish(Build::now());

        self::assertTrue($post->isPublished());
        $this->expectException(NewsPostAlreadyPublished::class);
        $post->edit('Zpět', 'x', 0, false, Build::now());
    }

    public function test_a_program_item_cannot_end_before_it_starts(): void
    {
        $start = new \DateTimeImmutable('2026-11-21 10:00:00+01:00');

        $this->expectException(InvalidCatalogData::class);
        new ProgramItemDetails('Rozcvička', null, null, null, $start, $start->modify('-1 hour'), null, ProgramVisibility::Public);
    }

    public function test_a_program_item_records_importance_and_liveness(): void
    {
        $details = new ProgramItemDetails('Rozcvička', null, 'Louka', 'Petr', new \DateTimeImmutable('2026-11-21 07:30:00+01:00'), null, null, ProgramVisibility::Participants);

        $item = ProgramItem::create(ProgramItemId::generate(), OrganizerId::generate(), EventId::generate(), $details, true, true, Build::now());

        [$recorded] = $item->releaseEvents();
        self::assertSame('added', $recorded->action);
        self::assertTrue($recorded->important);
        self::assertTrue($recorded->live);
    }
}
