<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\Membership;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Eventor\Identity\Domain\Event\MembershipGranted;
use Eventor\Identity\Domain\Event\MembershipRevoked;
use Eventor\Identity\Domain\Event\MembershipRoleChanged;
use Eventor\Shared\Domain\Event\RecordsEvents;
use Eventor\Shared\Domain\Event\RecordsEventsInterface;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\MembershipId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\UserAccountId;
use Eventor\Shared\Domain\Tenant\TenantScoped;

/**
 * "Account X holds role R in organizer O", optionally restricted to some events of the organizer (ORG-03). The first
 * tenant-scoped entity: the Doctrine `organizer` filter applies to it. Modules reference accounts and organizers by
 * id only, never by association.
 */
#[ORM\Entity]
#[ORM\Table(name: 'membership', schema: 'identity')]
#[ORM\UniqueConstraint(name: 'membership_account_organizer_key', columns: ['user_account_id', 'organizer_id'])]
#[ORM\Index(name: 'membership_organizer_idx', columns: ['organizer_id'])]
class Membership implements TenantScoped, RecordsEventsInterface
{
    use RecordsEvents;

    /** @var Collection<int, MembershipEventScope> */
    #[ORM\OneToMany(targetEntity: MembershipEventScope::class, mappedBy: 'membership', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $scope;

    /**
     * @param list<EventId> $eventScope
     */
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
        array $eventScope = [],
    ) {
        $this->scope = new ArrayCollection();
        $this->replaceScope($eventScope);
    }

    /**
     * @param list<EventId> $eventScope
     */
    public static function grant(MembershipId $id, UserAccountId $userAccountId, OrganizerId $organizerId, Role $role, \DateTimeImmutable $now, array $eventScope = []): self
    {
        $eventScope = self::assertScopeAllowed($role, $eventScope);
        $membership = new self($id, $userAccountId, $organizerId, $role, $now, $eventScope);
        $membership->record(new MembershipGranted($id, $userAccountId, $organizerId, $role, $eventScope));

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

    /**
     * Events this membership is restricted to; an empty list means all events of the organizer.
     *
     * @return list<EventId>
     */
    public function eventScope(): array
    {
        return array_values(array_map(static fn(MembershipEventScope $row): EventId => $row->eventId(), $this->scope->toArray()));
    }

    /**
     * The event scope as the single rule that voters apply: unrestricted, or the event is listed.
     */
    public function mayAccessEvent(EventId $event): bool
    {
        $scope = $this->eventScope();
        if ([] === $scope) {
            return true;
        }

        foreach ($scope as $allowed) {
            if ($allowed->equals($event)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<EventId> $eventScope
     */
    public function change(Role $role, array $eventScope): void
    {
        $eventScope = self::assertScopeAllowed($role, $eventScope);
        $previousRole = $this->role;
        $previousScope = $this->eventScope();

        $this->role = $role;
        $this->replaceScope($eventScope);

        $this->record(new MembershipRoleChanged($this->id, $this->userAccountId, $this->organizerId, $previousRole, $role, $previousScope, $eventScope));
    }

    public function revoke(): void
    {
        $this->record(new MembershipRevoked($this->id, $this->userAccountId, $this->organizerId, $this->role, $this->eventScope()));
    }

    /**
     * @param list<EventId> $eventScope
     *
     * @return list<EventId> the scope without duplicates
     */
    private static function assertScopeAllowed(Role $role, array $eventScope): array
    {
        $unique = [];
        foreach ($eventScope as $event) {
            $unique[$event->toString()] = $event;
        }
        $unique = array_values($unique);

        if ([] !== $unique && !\in_array($role, [Role::EventManager, Role::CheckIn], true)) {
            throw InvalidEventScope::notAllowedFor($role);
        }

        return $unique;
    }

    /**
     * @param list<EventId> $eventScope
     */
    private function replaceScope(array $eventScope): void
    {
        foreach ($this->scope->toArray() as $row) {
            if (!\in_array($row->eventId()->toString(), array_map(static fn(EventId $e): string => $e->toString(), $eventScope), true)) {
                $this->scope->removeElement($row);
            }
        }
        $kept = array_map(static fn(MembershipEventScope $row): string => $row->eventId()->toString(), $this->scope->toArray());
        foreach ($eventScope as $event) {
            if (!\in_array($event->toString(), $kept, true)) {
                $this->scope->add(new MembershipEventScope($this, $event, $this->organizerId));
            }
        }
    }
}
