<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Catalog;

use Eventor\Catalog\Application\EventPublisher\PublishActivityRescheduled;
use Eventor\Catalog\Application\EventPublisher\PublishCapacityChanged;
use Eventor\Catalog\Application\EventPublisher\PublishEventLifecycle;
use Eventor\Catalog\Application\EventPublisher\PublishFormVersionPublished;
use Eventor\Catalog\Application\EventPublisher\PublishNewsPublished;
use Eventor\Catalog\Application\EventPublisher\PublishProgramItemChanged;
use Eventor\Catalog\Application\EventPublisher\PublishRegistrationStateChanged;
use Eventor\Catalog\Contract\Event\ActivityRescheduled;
use Eventor\Catalog\Contract\Event\CapacityChanged;
use Eventor\Catalog\Contract\Event\EventCancelled;
use Eventor\Catalog\Contract\Event\EventCompleted;
use Eventor\Catalog\Contract\Event\EventDetailsChanged;
use Eventor\Catalog\Contract\Event\EventPublished;
use Eventor\Catalog\Contract\Event\FormVersionPublished;
use Eventor\Catalog\Contract\Event\NewsPublished;
use Eventor\Catalog\Contract\Event\ProgramItemChanged;
use Eventor\Catalog\Contract\Event\RegistrationStateChanged;
use Eventor\Catalog\Contract\PublicationState;
use Eventor\Catalog\Contract\RegistrationState;
use Eventor\Catalog\Domain\Event\ActivityTimeChanged;
use Eventor\Catalog\Domain\Event\CapacityLimitChanged;
use Eventor\Catalog\Domain\Event\EventDetailsUpdated;
use Eventor\Catalog\Domain\Event\FormVersionFrozen;
use Eventor\Catalog\Domain\Event\NewsPostModified;
use Eventor\Catalog\Domain\Event\PaymentRulesChanged;
use Eventor\Catalog\Domain\Event\PriceListChanged;
use Eventor\Catalog\Domain\Event\ProgramItemModified;
use Eventor\Catalog\Domain\Event\PublicationChanged;
use Eventor\Catalog\Domain\Event\RegistrationWindowChanged;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Eventor\Shared\Domain\Capacity\CapacityScope;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\CapacityBucketId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\FormVersionId;
use Eventor\Shared\Domain\Id\NewsPostId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ProgramItemId;
use Eventor\Tests\Support\Catalog\Build;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The translation of the internal domain events into the integration events of the catalogue (chapter 06): which
 * event leaves, with what payload, in whose tenant, and when nothing is announced (drafts).
 */
final class IntegrationEventPublishersTest extends TestCase
{
    private OrganizerId $org;
    private EventId $event;
    private MockClock $clock;
    /** @var list<array{object, array<\Symfony\Component\Messenger\Stamp\StampInterface>}> */
    private array $sent = [];
    private MessageBusInterface $bus;

    protected function setUp(): void
    {
        $this->org = OrganizerId::generate();
        $this->event = EventId::generate();
        $this->clock = new MockClock('2026-10-25 12:00:00');
        $this->sent = [];
        $this->bus = new class($this) implements MessageBusInterface {
            public function __construct(private readonly IntegrationEventPublishersTest $test) {}

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->test->capture($message, $stamps);

                return new Envelope($message, $stamps);
            }
        };
    }

    /**
     * @param array<\Symfony\Component\Messenger\Stamp\StampInterface> $stamps
     */
    public function capture(object $message, array $stamps): void
    {
        $this->sent[] = [$message, $stamps];
    }

    public function test_the_publication_becomes_event_published_with_the_activities(): void
    {
        $activity = ActivityId::generate();
        $starts = new \DateTimeImmutable('2026-11-21 08:00:00+01:00');
        $ends = new \DateTimeImmutable('2026-11-22 16:00:00+01:00');

        $this->lifecycle()->onPublicationChanged(new PublicationChanged($this->org, $this->event, PublicationState::Draft, PublicationState::Published, 'vikend', $starts, $ends, [$activity]));

        $message = $this->single(EventPublished::class);
        self::assertSame('vikend', $message->slug);
        self::assertSame([$activity], $message->activityIds);
        self::assertSame($this->event, $message->eventId);
        self::assertEquals($this->clock->now(), $message->occurredAt);
        self::assertSame($this->org->toString(), $this->tenantOfLast());
    }

    public function test_cancel_and_complete_become_their_events_and_archive_announces_nothing(): void
    {
        $at = new \DateTimeImmutable('2026-11-21 08:00:00+01:00');
        $lifecycle = $this->lifecycle();

        $lifecycle->onPublicationChanged(new PublicationChanged($this->org, $this->event, PublicationState::Published, PublicationState::Cancelled, 's', $at, $at, [], 'Bouřka'));
        $lifecycle->onPublicationChanged(new PublicationChanged($this->org, $this->event, PublicationState::Published, PublicationState::Completed, 's', $at, $at, []));
        $lifecycle->onPublicationChanged(new PublicationChanged($this->org, $this->event, PublicationState::Completed, PublicationState::Archived, 's', $at, $at, []));

        self::assertCount(2, $this->sent);
        self::assertInstanceOf(EventCancelled::class, $this->sent[0][0]);
        self::assertSame('Bouřka', $this->sent[0][0]->reason);
        self::assertInstanceOf(EventCompleted::class, $this->sent[1][0]);
    }

    public function test_detail_changes_of_a_draft_are_not_announced_but_those_of_a_live_event_are(): void
    {
        $lifecycle = $this->lifecycle();

        $lifecycle->onDetailsUpdated(new EventDetailsUpdated($this->org, $this->event, ['name'], false));
        $lifecycle->onDetailsUpdated(new EventDetailsUpdated($this->org, $this->event, [], true));
        $lifecycle->onPaymentRulesChanged(new PaymentRulesChanged($this->org, $this->event, [], [], false));
        $lifecycle->onPriceListChanged(new PriceListChanged($this->org, $this->event, 2, false));
        self::assertSame([], $this->sent);

        $lifecycle->onDetailsUpdated(new EventDetailsUpdated($this->org, $this->event, ['name', 'venues'], true));
        $lifecycle->onPaymentRulesChanged(new PaymentRulesChanged($this->org, $this->event, [], [], true));
        $lifecycle->onPriceListChanged(new PriceListChanged($this->org, $this->event, 2, true));

        self::assertSame([['name', 'venues'], ['payment_rules'], ['price_list']], array_map(static fn(array $pair): array => $pair[0] instanceof EventDetailsChanged ? $pair[0]->changedFields : [], $this->sent));
    }

    public function test_a_registration_window_transition_is_announced_with_its_levels(): void
    {
        $activity = ActivityId::generate();

        (new PublishRegistrationStateChanged($this->bus, $this->clock))(new RegistrationWindowChanged($this->org, $this->event, $activity, RegistrationState::NotOpen, RegistrationState::Open));

        $message = $this->single(RegistrationStateChanged::class);
        self::assertSame($activity, $message->activityId);
        self::assertSame(RegistrationState::NotOpen, $message->from);
        self::assertSame(RegistrationState::Open, $message->to);
    }

    public function test_only_a_moved_start_of_a_live_activity_is_a_reschedule(): void
    {
        $publisher = new PublishActivityRescheduled($this->bus, $this->clock);
        $activity = ActivityId::generate();
        $before = new \DateTimeImmutable('2026-11-22 10:00:00+01:00');
        $after = new \DateTimeImmutable('2026-11-22 11:00:00+01:00');

        $publisher(new ActivityTimeChanged($this->org, $this->event, $activity, $before, $after, true, false));
        $publisher(new ActivityTimeChanged($this->org, $this->event, $activity, null, $after, true, true));
        self::assertSame([], $this->sent, 'a draft, or a first start time, is no reschedule');

        $publisher(new ActivityTimeChanged($this->org, $this->event, $activity, $before, $after, true, true));

        $message = $this->single(ActivityRescheduled::class);
        self::assertTrue($message->important);
        self::assertEquals($before, $message->previousStartsAt);
    }

    public function test_a_limit_change_is_announced_only_when_the_bucket_exists(): void
    {
        $publisher = new PublishCapacityChanged($this->bus, $this->clock);
        $bucket = CapacityBucketId::generate();

        $publisher(new CapacityLimitChanged($this->org, $this->event, CapacityScope::Variant, 'v', $bucket, 100, 50, true, false));
        self::assertSame([], $this->sent);

        $publisher(new CapacityLimitChanged($this->org, $this->event, CapacityScope::Variant, 'v', $bucket, 100, 50, true, true, 'Menší hřiště'));

        $message = $this->single(CapacityChanged::class);
        self::assertSame($bucket, $message->bucketId);
        self::assertSame(CapacityScope::Variant, $message->scope);
        self::assertSame([100, 50, true], [$message->previousLimit, $message->newLimit, $message->belowOccupancy]);
    }

    public function test_a_frozen_form_version_lists_the_added_required_fields(): void
    {
        $version = FormVersionId::generate();
        $previous = FormVersionId::generate();

        (new PublishFormVersionPublished($this->bus, $this->clock))(new FormVersionFrozen($this->org, $this->event, $version, 2, $previous, ['dog_name'], str_repeat('a', 64)));

        $message = $this->single(FormVersionPublished::class);
        self::assertSame($version, $message->formVersionId);
        self::assertSame($previous, $message->previousFormVersionId);
        self::assertSame(['dog_name'], $message->addedRequiredFieldIds);
    }

    public function test_program_changes_are_announced_for_a_live_event_with_their_importance(): void
    {
        $publisher = new PublishProgramItemChanged($this->bus, $this->clock);
        $item = ProgramItemId::generate();

        $publisher(new ProgramItemModified($this->org, $this->event, $item, null, 'added', true, false));
        self::assertSame([], $this->sent);

        $publisher(new ProgramItemModified($this->org, $this->event, $item, null, 'updated', true, true));

        $message = $this->single(ProgramItemChanged::class);
        self::assertTrue($message->important);
        self::assertNull($message->activityId);
    }

    public function test_only_a_publication_of_news_is_announced(): void
    {
        $publisher = new PublishNewsPublished($this->bus, $this->clock);
        $post = NewsPostId::generate();

        $publisher(new NewsPostModified($this->org, $this->event, $post, 'created', true));
        $publisher(new NewsPostModified($this->org, $this->event, $post, 'updated', true));
        self::assertSame([], $this->sent);

        $publisher(new NewsPostModified($this->org, $this->event, $post, 'published', true));

        self::assertTrue($this->single(NewsPublished::class)->broadcast);
    }

    public function test_the_entities_record_what_the_publishers_translate(): void
    {
        $event = Build::event($this->org);
        $activity = Build::activity($event, limit: 100);

        $names = array_map(static fn(object $e): string => $e::class, [...$event->releaseEvents(), ...$activity->releaseEvents()]);

        self::assertSame(['Eventor\Catalog\Domain\Event\EventCreated', 'Eventor\Catalog\Domain\Event\ActivityChanged'], $names);
    }

    private function lifecycle(): PublishEventLifecycle
    {
        return new PublishEventLifecycle($this->bus, $this->clock);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function single(string $class): object
    {
        self::assertCount(1, $this->sent);
        self::assertInstanceOf($class, $this->sent[0][0]);

        return $this->sent[0][0];
    }

    private function tenantOfLast(): ?string
    {
        foreach (end($this->sent)[1] ?? [] as $stamp) {
            if ($stamp instanceof TenantStamp) {
                return $stamp->organizerId;
            }
        }

        return null;
    }
}
