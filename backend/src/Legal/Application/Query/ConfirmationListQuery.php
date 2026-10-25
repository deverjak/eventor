<?php

declare(strict_types=1);

namespace Eventor\Legal\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * The confirmation ledger as the admin sees it (LEG-07): every row, with a flag telling whether it is the current
 * decision of its chain. Ids only, no names; the full participant view belongs to Registration.
 */
final readonly class ConfirmationListQuery
{
    public function __construct(private Connection $connection) {}

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function list(OrganizerId $organizer, ?string $requirementId, ?string $participationId, ?string $orderId, ?string $decision, bool $onlyCurrent, int $limit, int $offset): array
    {
        $where = 'c.organizer_id = :organizer
            AND (CAST(:requirement AS text) IS NULL OR c.requirement_id = CAST(:requirement AS uuid))
            AND (CAST(:participation AS text) IS NULL OR c.participation_id = CAST(:participation AS uuid))
            AND (CAST(:order AS text) IS NULL OR c.order_id = CAST(:order AS uuid))
            AND (CAST(:decision AS text) IS NULL OR c.decision = :decision)
            AND (NOT :current OR NOT EXISTS (SELECT 1 FROM legal.confirmation n WHERE n.supersedes_id = c.id))';
        $params = ['organizer' => $organizer->toString(), 'requirement' => $requirementId, 'participation' => $participationId, 'order' => $orderId, 'decision' => $decision, 'current' => $onlyCurrent];
        $types = ['current' => \Doctrine\DBAL\ParameterType::BOOLEAN];

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT c.id, c.requirement_id, r.type AS requirement_type, r.purpose, c.document_version_id, v.number AS version_number,
                    c.person_id, c.participation_id, c.order_id, c.decided_by_person_id, c.decision, c.channel, c.note, c.decided_at,
                    NOT EXISTS (SELECT 1 FROM legal.confirmation n WHERE n.supersedes_id = c.id) AS is_current
               FROM legal.confirmation c
               JOIN legal.legal_requirement r ON r.id = c.requirement_id
               JOIN legal.document_version v ON v.id = c.document_version_id
              WHERE '.$where.'
              ORDER BY c.decided_at DESC, c.id DESC
              LIMIT :limit OFFSET :offset',
            $params + ['limit' => $limit, 'offset' => $offset],
            $types,
        );
        $total = $this->connection->fetchOne('SELECT count(*) FROM legal.confirmation c WHERE '.$where, $params, $types);

        return [
            'items' => array_map(static fn(array $row): array => [
                'id' => $row['id'],
                'requirementId' => $row['requirement_id'],
                'requirementType' => $row['requirement_type'],
                'purpose' => $row['purpose'],
                'versionId' => $row['document_version_id'],
                'versionNumber' => Cells::int($row['version_number']),
                'personId' => $row['person_id'],
                'participationId' => $row['participation_id'],
                'orderId' => $row['order_id'],
                'decidedByPersonId' => $row['decided_by_person_id'],
                'decision' => $row['decision'],
                'channel' => $row['channel'],
                'note' => $row['note'],
                'decidedAt' => (new \DateTimeImmutable(Cells::string($row['decided_at'])))->format(\DATE_ATOM),
                'current' => (bool) $row['is_current'],
            ], $rows),
            'total' => (int) (is_numeric($total) ? $total : 0),
        ];
    }
}
