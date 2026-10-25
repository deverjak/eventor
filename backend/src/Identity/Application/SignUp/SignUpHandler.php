<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\SignUp;

use Eventor\Identity\Application\Port\PasswordHasher;
use Eventor\Identity\Domain\Account\EmailAlreadyRegistered;
use Eventor\Identity\Domain\Account\UserAccount;
use Eventor\Identity\Domain\Account\UserAccountRepository;
use Eventor\Identity\Domain\Membership\Membership;
use Eventor\Identity\Domain\Membership\MembershipRepository;
use Eventor\Identity\Domain\Membership\Role;
use Eventor\Identity\Domain\Organizer\Organizer;
use Eventor\Identity\Domain\Organizer\OrganizerPolicy;
use Eventor\Identity\Domain\Organizer\OrganizerPolicyRepository;
use Eventor\Identity\Domain\Organizer\OrganizerRepository;
use Eventor\Identity\Domain\Organizer\SlugAlreadyTaken;
use Eventor\Shared\Domain\Id\MembershipId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\UserAccountId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class SignUpHandler
{
    public function __construct(
        private UserAccountRepository $accounts,
        private OrganizerRepository $organizers,
        private OrganizerPolicyRepository $policies,
        private MembershipRepository $memberships,
        private PasswordHasher $hasher,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{organizerId: string, userAccountId: string, membershipId: string, email: string}
     */
    public function __invoke(SignUp $command): array
    {
        if (null !== $this->accounts->findByEmail($command->email)) {
            throw EmailAlreadyRegistered::create();
        }
        if (null !== $this->organizers->findBySlug($command->slug)) {
            throw SlugAlreadyTaken::create($command->slug);
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $account = UserAccount::register(
            UserAccountId::generate(),
            $command->email,
            $this->hasher->hash($command->password),
            $command->displayName,
            $now,
        );
        $this->accounts->add($account);

        $organizer = Organizer::register(OrganizerId::generate(), $command->slug, $command->organizerName, $now, $account->email());
        $this->organizers->add($organizer);
        $this->policies->add(OrganizerPolicy::defaultsFor($organizer->id()));

        $membership = Membership::grant(MembershipId::generate(), $account->id(), $organizer->id(), Role::Owner, $now);
        $this->memberships->add($membership);

        return [
            'organizerId' => $organizer->id()->toString(),
            'userAccountId' => $account->id()->toString(),
            'membershipId' => $membership->id()->toString(),
            'email' => $account->email(),
        ];
    }
}
