<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\Access;

use Doctrine\DBAL\Connection;
use Eventor\Identity\Contract\AccessPolicy;
use Eventor\Identity\Contract\Permission;
use Eventor\Identity\Domain\Membership\Role;
use Eventor\Shared\Domain\Id\OrganizerId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Answers "may this user do X in this organizer?" from the membership role and the permission matrix.
 * The role lookup is memoised per request (reset between requests in worker mode).
 */
#[AsAlias(AccessPolicy::class)]
final class MembershipAccessPolicy implements AccessPolicy, ResetInterface
{
    /** @var array<string, string|null> */
    private array $roles = [];

    public function __construct(private readonly Connection $connection) {}

    public function isAllowed(Permission $permission, OrganizerId $organizer, string $userIdentifier): bool
    {
        $role = $this->roleOf($organizer, $userIdentifier);
        if (null === $role) {
            return false;
        }

        return PermissionMatrix::allows(Role::from($role), $permission, OrganizerPolicy::defaults());
    }

    public function roleOf(OrganizerId $organizer, string $userIdentifier): ?string
    {
        $key = $organizer->toString().'|'.$userIdentifier;
        if (\array_key_exists($key, $this->roles)) {
            return $this->roles[$key];
        }

        $role = $this->connection->fetchOne(
            'SELECT m.role
               FROM identity.membership m
               JOIN identity.user_account u ON u.id = m.user_account_id
              WHERE m.organizer_id = :organizer AND u.email = :email AND u.disabled_at IS NULL',
            ['organizer' => $organizer->toString(), 'email' => mb_strtolower($userIdentifier)],
        );

        return $this->roles[$key] = \is_string($role) ? $role : null;
    }

    public function reset(): void
    {
        $this->roles = [];
    }
}
