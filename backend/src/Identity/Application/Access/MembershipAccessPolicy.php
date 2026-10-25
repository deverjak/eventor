<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\Access;

use Doctrine\DBAL\Connection;
use Eventor\Identity\Contract\AccessPolicy;
use Eventor\Identity\Contract\Permission;
use Eventor\Identity\Domain\Membership\Role;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Answers "may this user do X in this organizer (and event)?" from the membership role, the event scope and the
 * permission matrix with the organizer policy exceptions. The membership lookup is memoised per request (reset
 * between requests in worker mode); the policy is read only for the two conditional cells.
 */
#[AsAlias(AccessPolicy::class)]
final class MembershipAccessPolicy implements AccessPolicy, ResetInterface
{
    /** @var array<string, array{role: string, scope: list<string>}|null> */
    private array $memberships = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly OrganizerPolicyProvider $policies,
    ) {}

    public function isAllowed(Permission $permission, OrganizerId $organizer, string $userIdentifier): bool
    {
        $membership = $this->membership($organizer, $userIdentifier);
        if (null === $membership) {
            return false;
        }

        $role = Role::from($membership['role']);
        if (PermissionMatrix::dependsOnPolicy($role, $permission)) {
            return PermissionMatrix::allows($role, $permission, $this->policies->forOrganizer($organizer));
        }

        return PermissionMatrix::allows($role, $permission, OrganizerPolicy::defaults());
    }

    public function isAllowedForEvent(Permission $permission, OrganizerId $organizer, EventId $event, string $userIdentifier): bool
    {
        if (!$this->isAllowed($permission, $organizer, $userIdentifier)) {
            return false;
        }

        $scope = $this->membership($organizer, $userIdentifier)['scope'] ?? [];

        return [] === $scope || \in_array($event->toString(), $scope, true);
    }

    public function roleOf(OrganizerId $organizer, string $userIdentifier): ?string
    {
        return $this->membership($organizer, $userIdentifier)['role'] ?? null;
    }

    public function eventScope(OrganizerId $organizer, string $userIdentifier): array
    {
        return array_map(
            static fn(string $id): EventId => EventId::fromString($id),
            $this->membership($organizer, $userIdentifier)['scope'] ?? [],
        );
    }

    public function reset(): void
    {
        $this->memberships = [];
    }

    /**
     * @return array{role: string, scope: list<string>}|null
     */
    private function membership(OrganizerId $organizer, string $userIdentifier): ?array
    {
        $key = $organizer->toString().'|'.$userIdentifier;
        if (\array_key_exists($key, $this->memberships)) {
            return $this->memberships[$key];
        }

        /** @var array{id: string, role: string}|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT m.id, m.role
               FROM identity.membership m
               JOIN identity.user_account u ON u.id = m.user_account_id
              WHERE m.organizer_id = :organizer AND u.email = :email AND u.disabled_at IS NULL',
            ['organizer' => $organizer->toString(), 'email' => mb_strtolower($userIdentifier)],
        );
        if (false === $row) {
            return $this->memberships[$key] = null;
        }

        /** @var list<string> $scope */
        $scope = $this->connection->fetchFirstColumn(
            'SELECT event_id FROM identity.membership_event_scope WHERE membership_id = :membership ORDER BY event_id',
            ['membership' => $row['id']],
        );

        return $this->memberships[$key] = ['role' => $row['role'], 'scope' => $scope];
    }
}
