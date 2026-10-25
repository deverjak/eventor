<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\CreateOwner;

use Eventor\Identity\Application\Port\PasswordHasher;
use Eventor\Identity\Domain\Account\UserAccount;
use Eventor\Identity\Domain\Account\UserAccountId;
use Eventor\Identity\Domain\Account\UserAccountRepository;
use Eventor\Identity\Domain\Membership\Membership;
use Eventor\Identity\Domain\Membership\MembershipId;
use Eventor\Identity\Domain\Membership\MembershipRepository;
use Eventor\Identity\Domain\Membership\Role;
use Eventor\Identity\Domain\Organizer\Organizer;
use Eventor\Identity\Domain\Organizer\OrganizerRepository;
use Eventor\Identity\Domain\Organizer\SlugAlreadyTaken;
use Eventor\Shared\Domain\Id\OrganizerId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateOwnerHandler
{
    public function __construct(
        private UserAccountRepository $accounts,
        private OrganizerRepository $organizers,
        private MembershipRepository $memberships,
        private PasswordHasher $hasher,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{organizerId: string, userAccountId: string, membershipId: string}
     */
    public function __invoke(CreateOwner $command): array
    {
        if (null !== $this->organizers->findBySlug($command->slug)) {
            throw SlugAlreadyTaken::create($command->slug);
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $account = $this->accounts->findByEmail($command->email);
        if (null === $account) {
            $account = UserAccount::register(
                UserAccountId::generate(),
                $command->email,
                $this->hasher->hash($command->password),
                $command->displayName,
                $now,
            );
            $this->accounts->add($account);
        }

        $organizer = Organizer::register(OrganizerId::generate(), $command->slug, $command->organizerName, $now);
        $this->organizers->add($organizer);

        $membership = Membership::grant(MembershipId::generate(), $account->id(), $organizer->id(), Role::Owner, $now);
        $this->memberships->add($membership);

        return [
            'organizerId' => $organizer->id()->toString(),
            'userAccountId' => $account->id()->toString(),
            'membershipId' => $membership->id()->toString(),
        ];
    }
}
