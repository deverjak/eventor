<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\EventPublisher;

use Eventor\Identity\Contract\Event\MembershipChanged;
use Eventor\Identity\Domain\Event\MembershipGranted;
use Eventor\Identity\Domain\Event\MembershipRevoked;
use Eventor\Identity\Domain\Event\MembershipRoleChanged;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\MembershipId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\UserAccountId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Translates the internal membership domain events into the public integration event `MembershipChanged`. It runs
 * synchronously inside the command transaction; `MembershipChanged` is routed to the transports of its subscribers,
 * so the INSERT into the outbox commits (or rolls back) together with the membership.
 */
final readonly class PublishMembershipChanged
{
    public function __construct(
        private MessageBusInterface $eventBus,
        private ClockInterface $clock,
    ) {}

    #[AsMessageHandler(bus: 'event.bus')]
    public function onGranted(MembershipGranted $event): void
    {
        $this->publish($event->organizerId, $event->membershipId, $event->userAccountId, null, $event->role->value, $event->eventScope);
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onRoleChanged(MembershipRoleChanged $event): void
    {
        $this->publish($event->organizerId, $event->membershipId, $event->userAccountId, $event->previousRole->value, $event->role->value, $event->eventScope);
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onRevoked(MembershipRevoked $event): void
    {
        $this->publish($event->organizerId, $event->membershipId, $event->userAccountId, $event->role->value, null, $event->eventScope);
    }

    /**
     * @param list<EventId> $eventScope
     */
    private function publish(OrganizerId $organizer, MembershipId $membership, UserAccountId $account, ?string $previousRole, ?string $newRole, array $eventScope): void
    {
        $this->eventBus->dispatch(
            new MembershipChanged($organizer, $membership, $account, $previousRole, $newRole, $eventScope, $this->clock->now()),
            [TenantStamp::of($organizer)],
        );
    }
}
