<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Scheduling;

use Doctrine\DBAL\Connection;

/**
 * What the sweeper has to do now, across organizers (a DBAL read; the tenant of each item is then entered explicitly).
 */
final readonly class DueWork
{
    private const int BATCH = 500;

    public function __construct(private Connection $connection) {}

    /**
     * @return list<array{id: string, organizerId: string}>
     */
    public function deliveries(\DateTimeImmutable $now): array
    {
        /** @var list<array{id: string, organizer_id: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, organizer_id FROM communication.delivery WHERE state = 'scheduled' AND dispatched_at IS NULL AND send_after <= :now ORDER BY send_after LIMIT ".self::BATCH,
            ['now' => $now->format('Y-m-d H:i:sP')],
        );

        return array_map(static fn(array $row): array => ['id' => $row['id'], 'organizerId' => $row['organizer_id']], $rows);
    }

    /**
     * @return list<array{id: string, organizerId: string}>
     */
    public function campaigns(\DateTimeImmutable $now): array
    {
        /** @var list<array{id: string, organizer_id: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, organizer_id FROM communication.campaign WHERE state = 'scheduled' AND scheduled_at <= :now ORDER BY scheduled_at LIMIT ".self::BATCH,
            ['now' => $now->format('Y-m-d H:i:sP')],
        );

        return array_map(static fn(array $row): array => ['id' => $row['id'], 'organizerId' => $row['organizer_id']], $rows);
    }
}
