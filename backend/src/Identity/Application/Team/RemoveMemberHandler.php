<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\Team;

use Eventor\Identity\Domain\Membership\LastOwnerRequired;
use Eventor\Identity\Domain\Membership\MembershipNotFound;
use Eventor\Identity\Domain\Membership\MembershipRepository;
use Eventor\Identity\Domain\Membership\Role;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RemoveMemberHandler
{
    public function __construct(private MembershipRepository $memberships) {}

    public function __invoke(RemoveMember $command): void
    {
        $membership = $this->memberships->find($command->membershipId, $command->organizerId) ?? throw MembershipNotFound::forId($command->membershipId);

        if (Role::Owner === $membership->role() && $this->memberships->countOwners($command->organizerId) <= 1) {
            throw LastOwnerRequired::create();
        }

        $membership->revoke();
        $this->memberships->remove($membership);
    }
}
