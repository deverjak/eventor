<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\EventPublisher;

use Eventor\Identity\Contract\Event\MemberInvited;
use Eventor\Identity\Domain\Event\InvitationCreated;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * `InvitationCreated` -> `identity.member_invited` (ids and role only; Communication reads the e-mail address and the
 * accept link through `InvitationQuery`).
 */
#[AsMessageHandler(bus: 'event.bus')]
final readonly class PublishMemberInvited
{
    public function __construct(
        private MessageBusInterface $eventBus,
        private ClockInterface $clock,
    ) {}

    public function __invoke(InvitationCreated $event): void
    {
        $this->eventBus->dispatch(
            new MemberInvited($event->organizerId, $event->invitationId, $event->role->value, $this->clock->now()),
            [TenantStamp::of($event->organizerId)],
        );
    }
}
