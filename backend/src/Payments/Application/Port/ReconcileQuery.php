<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Port;

/**
 * Cross-tenant read of the requests the reconcile job re-fetches from Pairrot (system job, no organizer in scope).
 */
interface ReconcileQuery
{
    /**
     * Issued, not cancelled, not fully settled requests older than `$olderThan`.
     *
     * @return list<array{pairrotId: string, subtenantId: string, externalId: string, lastSequence: int}>
     */
    public function openRequestsIssuedBefore(\DateTimeImmutable $olderThan, int $limit): array;
}
