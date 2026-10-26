<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Hold;

use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * `payments.payment_request_issued` (chapter 04, path 3): when Pairrot was down and the payment instructions came late, the
 * reservation is prolonged so that the participant has at least 48 hours from the moment the instructions exist, but never
 * beyond the close of the registration.
 */
final readonly class ExtendHoldIfLate
{
    public const int MINIMUM_HOURS = 48;

    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public \DateTimeImmutable $issuedAt,
    ) {}
}
