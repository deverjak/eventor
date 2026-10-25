<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Event;

use Eventor\Payments\Contract\MatchingResult;
use Eventor\Shared\Domain\Id\BankTransactionId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Money;

/**
 * Domain event: a transaction entered the reconciliation queue. Translated into `payments.unmatched_transaction_received`.
 */
final readonly class UnmatchedTransactionRecorded
{
    public function __construct(
        public OrganizerId $organizerId,
        public BankTransactionId $transactionId,
        public Money $amount,
        public ?string $variableSymbol,
        public MatchingResult $matchingResult,
        public \DateTimeImmutable $bookedOn,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
