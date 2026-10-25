<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\Invite;

use Eventor\Identity\Application\Port\InvitationTokens;
use Eventor\Identity\Application\Port\PasswordHasher;
use Eventor\Identity\Contract\Exception\InvitationNotFound;
use Eventor\Identity\Contract\Exception\OrganizerNotFound;
use Eventor\Identity\Domain\Account\UserAccount;
use Eventor\Identity\Domain\Account\UserAccountRepository;
use Eventor\Identity\Domain\Invitation\AccountDetailsRequired;
use Eventor\Identity\Domain\Invitation\InvitationEmailMismatch;
use Eventor\Identity\Domain\Invitation\InvitationRepository;
use Eventor\Identity\Domain\Invitation\SignInRequired;
use Eventor\Identity\Domain\Membership\AlreadyMember;
use Eventor\Identity\Domain\Membership\Membership;
use Eventor\Identity\Domain\Membership\MembershipRepository;
use Eventor\Identity\Domain\Organizer\OrganizerRepository;
use Eventor\Shared\Domain\Id\MembershipId;
use Eventor\Shared\Domain\Id\UserAccountId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class AcceptInvitationHandler
{
    public function __construct(
        private InvitationRepository $invitations,
        private UserAccountRepository $accounts,
        private OrganizerRepository $organizers,
        private MembershipRepository $memberships,
        private InvitationTokens $tokens,
        private PasswordHasher $hasher,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{organizerId: string, organizerSlug: string, userAccountId: string, email: string, membershipId: string, accountCreated: bool}
     */
    public function __invoke(AcceptInvitation $command): array
    {
        $id = $this->tokens->idOf($command->token) ?? throw InvitationNotFound::forToken();
        $invitation = $this->invitations->find($id);
        if (null === $invitation || !hash_equals($invitation->tokenSha256(), $this->tokens->hash($command->token))) {
            throw InvitationNotFound::forToken();
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        if (!$invitation->isPending($now)) {
            throw InvitationNotFound::forId($id);
        }

        $account = $this->accounts->findByEmail($invitation->email());
        $created = false;
        if (null !== $command->signedInEmail) {
            if (UserAccount::normalizeEmail($command->signedInEmail) !== $invitation->email() || null === $account) {
                throw InvitationEmailMismatch::create();
            }
        } elseif (null !== $account) {
            throw SignInRequired::create();
        } else {
            if (null === $command->password || '' === $command->password || null === $command->displayName || '' === trim($command->displayName)) {
                throw AccountDetailsRequired::create();
            }
            $account = UserAccount::register(UserAccountId::generate(), $invitation->email(), $this->hasher->hash($command->password), trim($command->displayName), $now);
            $this->accounts->add($account);
            $created = true;
        }

        if ($this->memberships->exists($account->id(), $invitation->organizerId())) {
            throw AlreadyMember::create();
        }
        $organizer = $this->organizers->findById($invitation->organizerId()) ?? throw OrganizerNotFound::forId($invitation->organizerId());

        $membership = Membership::grant(MembershipId::generate(), $account->id(), $organizer->id(), $invitation->role(), $now, $invitation->eventScope());
        $this->memberships->add($membership);
        $invitation->accept($account->id(), $now);

        return [
            'organizerId' => $organizer->id()->toString(),
            'organizerSlug' => $organizer->slug(),
            'userAccountId' => $account->id()->toString(),
            'email' => $account->email(),
            'membershipId' => $membership->id()->toString(),
            'accountCreated' => $created,
        ];
    }
}
