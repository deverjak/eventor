<?php

declare(strict_types=1);

namespace Eventor\Payments\Contract\Event;

use Eventor\Payments\Contract\MatchingResult;
use Eventor\Shared\Domain\Id\BankTransactionId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Money;

/**
 * A bank transaction Pairrot could not allocate arrived (PAY-06): it enters the reconciliation queue. `bookedOn` is a calendar date (midnight UTC).
 *
 * P0. Planned subscribers: attention.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'payments.unmatched_transaction_received', version: 1)]
final readonly class UnmatchedTransactionReceived
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
