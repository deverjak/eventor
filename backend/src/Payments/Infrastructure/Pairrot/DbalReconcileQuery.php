<?php

declare(strict_types=1);

namespace Eventor\Payments\Infrastructure\Pairrot;

use Doctrine\DBAL\Connection;
use Eventor\Payments\Application\Port\ReconcileQuery;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(ReconcileQuery::class)]
final readonly class DbalReconcileQuery implements ReconcileQuery
{
    public function __construct(private Connection $connection) {}

    public function openRequestsIssuedBefore(\DateTimeImmutable $olderThan, int $limit): array
    {
        /** @var list<array{pairrot_id: string, subtenant_id: string, external_id: string, last_sequence: int|string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            "SELECT pairrot_id, subtenant_id, external_id, last_sequence
               FROM payments.payment_request
              WHERE creation_state = 'CREATED' AND cancelled_at IS NULL AND status IN ('issued', 'open', 'partially_paid')
                AND issued_at < :before
              ORDER BY issued_at
              LIMIT :limit",
            ['before' => $olderThan->format('Y-m-d H:i:sP'), 'limit' => $limit],
            ['limit' => \Doctrine\DBAL\ParameterType::INTEGER],
        );

        return array_map(static fn(array $row): array => [
            'pairrotId' => $row['pairrot_id'],
            'subtenantId' => $row['subtenant_id'],
            'externalId' => $row['external_id'],
            'lastSequence' => (int) $row['last_sequence'],
        ], $rows);
    }
}
