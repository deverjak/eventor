<?php

declare(strict_types=1);

namespace Eventor\Communication\UserInterface\EventHandler;

use Eventor\Communication\Application\Scheduling\ScheduleDelivery;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Identity\Contract\Event\MemberInvited;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * ORG-03: the invitation message. The accept link carries a secret that exists only in Identity
 * (`InvitationQuery::forDelivery`), so it is built when the message is written, not here.
 */
#[AsMessageHandler(fromTransport: 'communication_events')]
final readonly class OnMemberInvited
{
    public function __construct(private CommandBus $commands) {}

    public function __invoke(MemberInvited $event): void
    {
        $this->commands->dispatch(new ScheduleDelivery(
            TemplateKey::MemberInvitation,
            'member_invitation:'.$event->invitationId->toString(),
            refId: $event->invitationId->toString(),
        ), TenantStamp::of($event->organizerId));
    }
}
