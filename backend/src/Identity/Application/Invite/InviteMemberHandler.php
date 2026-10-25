<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\Invite;

use Eventor\Identity\Application\Port\InvitationTokens;
use Eventor\Identity\Domain\Account\UserAccount;
use Eventor\Identity\Domain\Account\UserAccountRepository;
use Eventor\Identity\Domain\Invitation\Invitation;
use Eventor\Identity\Domain\Invitation\InvitationRepository;
use Eventor\Identity\Domain\Membership\AlreadyMember;
use Eventor\Identity\Domain\Membership\MembershipRepository;
use Eventor\Identity\Domain\Membership\Role;
use Eventor\Shared\Domain\Id\InvitationId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class InviteMemberHandler
{
    public function __construct(
        private InvitationRepository $invitations,
        private UserAccountRepository $accounts,
        private MembershipRepository $memberships,
        private InvitationTokens $tokens,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{invitationId: string, token: string, expiresAt: string}
     */
    public function __invoke(InviteMember $command): array
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $email = UserAccount::normalizeEmail($command->email);

        $existing = $this->accounts->findByEmail($email);
        if (null !== $existing && $this->memberships->exists($existing->id(), $command->organizerId)) {
            throw AlreadyMember::create();
        }

        foreach ($this->invitations->findPending($command->organizerId, $email, $now) as $pending) {
            $pending->revoke($now);
        }

        $id = InvitationId::generate();
        $token = $this->tokens->issue($id);
        $invitation = Invitation::create(
            $id,
            $command->organizerId,
            $email,
            Role::from($command->role),
            $command->eventScope,
            $this->tokens->hash($token),
            $command->invitedBy,
            $now,
        );
        $this->invitations->add($invitation);

        return [
            'invitationId' => $id->toString(),
            'token' => $token,
            'expiresAt' => $invitation->expiresAt()->format(\DATE_ATOM),
        ];
    }
}
