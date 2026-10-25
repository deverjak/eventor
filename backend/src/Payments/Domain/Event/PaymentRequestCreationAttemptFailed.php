<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Event;

use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\PaymentRequestId;

/**
 * Domain event: one attempt to create the request failed (it will be retried by the outbox). Counted for the UI;
 * nothing is published.
 */
final readonly class PaymentRequestCreationAttemptFailed
{
    public function __construct(
        public OrganizerId $organizerId,
        public PaymentRequestId $paymentRequestId,
        public int $failedAttempts,
        public string $error,
    ) {}
}
