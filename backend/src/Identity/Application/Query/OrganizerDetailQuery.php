<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Identity\Application\Query\View\OrganizerDetailView;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Read model of the admin organizer page. Takes the OrganizerId explicitly: Doctrine filters do not apply to DBAL
 * queries (ADR 005), so tenant scoping is visible in the signature.
 */
final readonly class OrganizerDetailQuery
{
    public function __construct(private Connection $connection) {}

    public function get(OrganizerId $organizer, string $role): OrganizerDetailView
    {
        /** @var array{id: string, slug: string, name: string, created_at: string, members: int|string}|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT o.id, o.slug, o.name, o.created_at,
                    (SELECT count(*) FROM identity.membership m WHERE m.organizer_id = o.id) AS members
               FROM identity.organizer o
              WHERE o.id = :id',
            ['id' => $organizer->toString()],
        );
        if (false === $row) {
            throw new \LogicException('Organizer vanished during the request.');
        }

        return new OrganizerDetailView(
            $row['id'],
            $row['slug'],
            $row['name'],
            new \DateTimeImmutable($row['created_at']),
            (int) $row['members'],
            $role,
        );
    }
}
