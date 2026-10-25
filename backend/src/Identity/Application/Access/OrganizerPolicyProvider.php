<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\Access;

use Doctrine\DBAL\Connection;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Reads the permission-relevant flags of `organizer_policy` (never memoised: a policy change applies at once).
 */
final readonly class OrganizerPolicyProvider
{
    public function __construct(private Connection $connection) {}

    public function forOrganizer(OrganizerId $organizer): OrganizerPolicy
    {
        /** @var array{check_in_may_record_cash: bool, event_manager_may_resolve_payments: bool}|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT check_in_may_record_cash, event_manager_may_resolve_payments FROM identity.organizer_policy WHERE organizer_id = :organizer',
            ['organizer' => $organizer->toString()],
        );

        return false === $row ? OrganizerPolicy::defaults() : new OrganizerPolicy((bool) $row['check_in_may_record_cash'], (bool) $row['event_manager_may_resolve_payments']);
    }
}
