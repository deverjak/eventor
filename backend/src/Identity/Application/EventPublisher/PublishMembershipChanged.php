<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\EventPublisher;

use Eventor\Identity\Contract\Event\MembershipChanged;
use Eventor\Identity\Domain\Event\MembershipGranted;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
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
    public function __construct(private MessageBusInterface $eventBus) {}

    public function __invoke(MembershipGranted $event): void
    {
        $this->eventBus->dispatch(
            new MembershipChanged(
                $event->organizerId->toString(),
                $event->membershipId->toString(),
                $event->userAccountId->toString(),
                $event->role->value,
                'granted',
            ),
            [TenantStamp::of($event->organizerId)],
        );
    }
}
