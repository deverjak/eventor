<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Port;

final readonly class AllocationRequest
{
    public function __construct(
        public string $subtenantId,
        public string $pairrotTransactionId,
        public string $pairrotRequestId,
        public string $idempotencyKey,
        public string $reason,
    ) {}
}
