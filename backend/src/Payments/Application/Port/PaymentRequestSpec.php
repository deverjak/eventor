<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Port;

use Eventor\Shared\Domain\Money;

/**
 * What Eventor asks Pairrot to expect. The VS is never sent: Pairrot assigns it (ADR 004). Replaying a creation needs
 * the identical spec, so the spec is built from the persisted mirror row, never from "now".
 */
final readonly class PaymentRequestSpec
{
    public function __construct(
        public string $subtenantId,
        public string $bankConnectionId,
        public string $externalId,
        public Money $amount,
        public string $message,
        public string $description,
        public \DateTimeImmutable $dueAt,
    ) {}
}
