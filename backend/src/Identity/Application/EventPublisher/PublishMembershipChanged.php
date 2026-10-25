<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\EventPublisher;

use Eventor\Identity\Contract\Event\MembershipChanged;
use Eventor\Identity\Domain\Event\MembershipGranted;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Translates the internal domain event into the public integration event. It runs synchronously inside the command
 * transaction; `MembershipChanged` is routed to the transports of its subscribers, so the INSERT into the outbox
 * commits (or rolls back) together with the membership.
 */
#[AsMessageHandler(bus: 'event.bus')]
final readonly class PublishMembershipChanged
{
    public function __construct(
        private MessageBusInterface $eventBus,
        private ClockInterface $clock,
    ) {}

    public function __invoke(MembershipGranted $event): void
    {
        $this->eventBus->dispatch(
            new MembershipChanged(
                $event->organizerId,
                $event->membershipId,
                $event->userAccountId,
                previousRole: null,
                newRole: $event->role->value,
                eventScope: [],
                occurredAt: $this->clock->now(),
            ),
            [TenantStamp::of($event->organizerId)],
        );
    }
}
