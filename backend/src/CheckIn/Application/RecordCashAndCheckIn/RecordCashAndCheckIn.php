<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Application\RecordCashAndCheckIn;

/**
 * Cash taken at the desk and the arrival in one transaction (CHK-08). `$idempotencyKey` also keys the cash entry of
 * Payments, so a repeated request records the money once.
 */
final readonly class RecordCashAndCheckIn
{
    public function __construct(
        public string $eventId,
        public string $listId,
        public string $participationId,
        public int $amountMinor,
        public ?string $stationId,
        public ?string $note,
        public string $idempotencyKey,
        public ?string $actorAccountId,
        public ?string $deviceLabel,
    ) {}
}
