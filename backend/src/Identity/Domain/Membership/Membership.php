<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\Membership;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Identity\Domain\Account\UserAccountId;
use Eventor\Identity\Domain\Event\MembershipGranted;
use Eventor\Shared\Domain\Event\RecordsEvents;
use Eventor\Shared\Domain\Event\RecordsEventsInterface;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Tenant\TenantScoped;

/**
 * "Account X holds role R in organizer O." The first tenant-scoped entity: the Doctrine `organizer` filter
 * applies to it. Modules reference accounts and organizers by id only, never by association.
 */
#[ORM\Entity]
#[ORM\Table(name: 'membership', schema: 'identity')]
#[ORM\UniqueConstraint(name: 'membership_account_organizer_key', columns: ['user_account_id', 'organizer_id'])]
#[ORM\Index(name: 'membership_organizer_idx', columns: ['organizer_id'])]
class Membership implements TenantScoped, RecordsEventsInterface
{
    use RecordsEvents;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'membership_id')]
        private MembershipId $id,
        #[ORM\Column(type: 'user_account_id')]
        private UserAccountId $userAccountId,
        #[ORM\Column(type: 'organizer_id')]
        private OrganizerId $organizerId,
        #[ORM\Column(type: 'string', length: 32, enumType: Role::class)]
        private Role $role,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $createdAt,
    ) {}

    public static function grant(MembershipId $id, UserAccountId $userAccountId, OrganizerId $organizerId, Role $role, \DateTimeImmutable $now): self
    {
        $membership = new self($id, $userAccountId, $organizerId, $role, $now);
        $membership->record(new MembershipGranted($id, $userAccountId, $organizerId, $role));

        return $membership;
    }

    public function id(): MembershipId
    {
        return $this->id;
    }

    public function userAccountId(): UserAccountId
    {
        return $this->userAccountId;
    }

    public function organizerId(): OrganizerId
    {
        return $this->organizerId;
    }

    public function role(): Role
    {
        return $this->role;
    }
}
