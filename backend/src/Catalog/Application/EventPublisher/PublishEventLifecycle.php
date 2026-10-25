<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\EventPublisher;

use Eventor\Catalog\Contract\Event\EventCancelled;
use Eventor\Catalog\Contract\Event\EventCompleted;
use Eventor\Catalog\Contract\Event\EventDetailsChanged;
use Eventor\Catalog\Contract\Event\EventPublished;
use Eventor\Catalog\Contract\PublicationState;
use Eventor\Catalog\Domain\Event\EventDetailsUpdated;
use Eventor\Catalog\Domain\Event\PaymentRulesChanged;
use Eventor\Catalog\Domain\Event\PriceListChanged;
use Eventor\Catalog\Domain\Event\PublicationChanged;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Translates the internal domain events of an event into the integration events of the catalogue
 * (`catalog.event_published`, `event_cancelled`, `event_completed`, `event_details_changed`). It runs synchronously in
 * the command transaction, so the outbox rows commit together with the change.
 */
final readonly class PublishEventLifecycle
{
    public function __construct(
        private MessageBusInterface $eventBus,
        private ClockInterface $clock,
    ) {}

    #[AsMessageHandler(bus: 'event.bus')]
    public function onPublicationChanged(PublicationChanged $event): void
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $message = match ($event->to) {
            PublicationState::Published => new EventPublished($event->organizerId, $event->eventId, $event->slug, $event->startsAt, $event->endsAt, $event->activityIds, $now),
            PublicationState::Cancelled => new EventCancelled($event->organizerId, $event->eventId, $event->reason ?? '', $now),
            PublicationState::Completed => new EventCompleted($event->organizerId, $event->eventId, $now),
            default => null,
        };
        if (null !== $message) {
            $this->eventBus->dispatch($message, [TenantStamp::of($event->organizerId)]);
        }
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onDetailsUpdated(EventDetailsUpdated $event): void
    {
        if ($event->live && [] !== $event->changedFields) {
            $this->details($event->organizerId, $event->eventId, $event->changedFields);
        }
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onPaymentRulesChanged(PaymentRulesChanged $event): void
    {
        if ($event->live) {
            $this->details($event->organizerId, $event->eventId, ['payment_rules']);
        }
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onPriceListChanged(PriceListChanged $event): void
    {
        if ($event->live) {
            $this->details($event->organizerId, $event->eventId, ['price_list']);
        }
    }

    /**
     * @param list<string> $fields
     */
    private function details(OrganizerId $organizer, EventId $event, array $fields): void
    {
        $this->eventBus->dispatch(
            new EventDetailsChanged($organizer, $event, $fields, \DateTimeImmutable::createFromInterface($this->clock->now())),
            [TenantStamp::of($organizer)],
        );
    }
}
