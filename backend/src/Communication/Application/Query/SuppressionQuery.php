<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Communication\Application\Query\View\SuppressionView;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * The suppression list without the addresses: only hashes are stored, so an entry is identified by its id.
 */
final readonly class SuppressionQuery
{
    public function __construct(private Connection $connection) {}

    /**
     * @return list<SuppressionView>
     */
    public function list(OrganizerId $organizer): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, scope, reason, created_at FROM communication.suppression WHERE organizer_id = :organizer ORDER BY created_at DESC, id DESC',
            ['organizer' => $organizer->toString()],
        );

        return array_map(static fn(array $row): SuppressionView => new SuppressionView(
            Row::string($row, 'id'),
            Row::string($row, 'scope'),
            Row::string($row, 'reason'),
            Row::date($row, 'created_at'),
        ), $rows);
    }
}
