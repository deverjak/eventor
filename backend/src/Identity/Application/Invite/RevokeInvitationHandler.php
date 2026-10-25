<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\Invite;

use Eventor\Identity\Contract\Exception\InvitationNotFound;
use Eventor\Identity\Domain\Invitation\InvitationRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RevokeInvitationHandler
{
    public function __construct(
        private InvitationRepository $invitations,
        private ClockInterface $clock,
    ) {}

    public function __invoke(RevokeInvitation $command): void
    {
        $invitation = $this->invitations->find($command->invitationId);
        if (null === $invitation || $invitation->isRevoked() || !$invitation->organizerId()->equals($command->organizerId)) {
            throw InvitationNotFound::forId($command->invitationId);
        }

        $invitation->revoke(\DateTimeImmutable::createFromInterface($this->clock->now()));
    }
}
