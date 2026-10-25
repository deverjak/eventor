<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Identity\Application\Query\View\PendingInvitationView;
use Eventor\Identity\Application\Query\View\TeamMemberView;
use Eventor\Shared\Domain\Id\OrganizerId;
use Psr\Clock\ClockInterface;

/**
 * Members and open invitations of an organizer (ORG-03).
 */
final readonly class TeamQuery
{
    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
    ) {}

    /**
     * @return list<TeamMemberView>
     */
    public function members(OrganizerId $organizer): array
    {
        /** @var list<array{id: string, user_account_id: string, email: string, display_name: string, role: string, created_at: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT m.id, m.user_account_id, u.email, u.display_name, m.role, m.created_at
               FROM identity.membership m
               JOIN identity.user_account u ON u.id = m.user_account_id
              WHERE m.organizer_id = :organizer
              ORDER BY m.created_at, m.id',
            ['organizer' => $organizer->toString()],
        );

        /** @var list<array{membership_id: string, event_id: string}> $scopeRows */
        $scopeRows = $this->connection->fetchAllAssociative(
            'SELECT membership_id, event_id FROM identity.membership_event_scope WHERE organizer_id = :organizer ORDER BY event_id',
            ['organizer' => $organizer->toString()],
        );
        $scopes = [];
        foreach ($scopeRows as $scope) {
            $scopes[$scope['membership_id']][] = $scope['event_id'];
        }

        return array_map(static fn(array $row): TeamMemberView => new TeamMemberView(
            $row['id'],
            $row['user_account_id'],
            $row['email'],
            $row['display_name'],
            $row['role'],
            $scopes[$row['id']] ?? [],
            (new \DateTimeImmutable($row['created_at']))->format(\DATE_ATOM),
        ), $rows);
    }

    /**
     * @return list<PendingInvitationView>
     */
    public function pendingInvitations(OrganizerId $organizer): array
    {
        /** @var list<array{id: string, email: string, role: string, event_ids: string, created_at: string, expires_at: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, email, role, event_ids, created_at, expires_at
               FROM identity.invitation
              WHERE organizer_id = :organizer AND accepted_at IS NULL AND revoked_at IS NULL AND expires_at > :now
              ORDER BY created_at, id',
            ['organizer' => $organizer->toString(), 'now' => $this->clock->now()->format(\DATE_ATOM)],
        );

        return array_map(static function (array $row): PendingInvitationView {
            /** @var list<string> $events */
            $events = json_decode($row['event_ids'], true, 512, \JSON_THROW_ON_ERROR);

            return new PendingInvitationView(
                $row['id'],
                $row['email'],
                $row['role'],
                $events,
                (new \DateTimeImmutable($row['created_at']))->format(\DATE_ATOM),
                (new \DateTimeImmutable($row['expires_at']))->format(\DATE_ATOM),
            );
        }, $rows);
    }
}
