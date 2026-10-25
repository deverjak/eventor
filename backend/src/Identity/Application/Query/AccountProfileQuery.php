<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Identity\Application\Query\View\AccountProfileView;
use Eventor\Identity\Application\Query\View\OrganizerMembershipView;

final readonly class AccountProfileQuery
{
    public function __construct(private Connection $connection) {}

    public function forUser(string $userIdentifier): ?AccountProfileView
    {
        /** @var array{id: string, email: string, display_name: string}|false $account */
        $account = $this->connection->fetchAssociative(
            'SELECT id, email, display_name FROM identity.user_account WHERE email = :email AND disabled_at IS NULL',
            ['email' => mb_strtolower($userIdentifier)],
        );
        if (false === $account) {
            return null;
        }

        /** @var list<array{organizer_id: string, slug: string, name: string, role: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT o.id AS organizer_id, o.slug, o.name, m.role
               FROM identity.membership m
               JOIN identity.organizer o ON o.id = m.organizer_id
              WHERE m.user_account_id = :account
              ORDER BY o.name, o.id',
            ['account' => $account['id']],
        );

        return new AccountProfileView(
            $account['id'],
            $account['email'],
            $account['display_name'],
            array_map(
                static fn(array $row): OrganizerMembershipView => new OrganizerMembershipView($row['organizer_id'], $row['slug'], $row['name'], $row['role']),
                $rows,
            ),
        );
    }
}
