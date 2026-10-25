<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Port;

use Eventor\Shared\Domain\Money;

/**
 * A payment request as Pairrot confirmed it, with the frozen payment instructions (`spaydPayload` is the only valid
 * QR content for this request, never rebuilt).
 */
final readonly class IssuedPaymentRequest
{
    public function __construct(
        public string $pairrotId,
        public string $externalId,
        public string $variableSymbol,
        public ?string $accountNumber,
        public ?string $iban,
        public Money $amount,
        public \DateTimeImmutable $dueAt,
        public string $spaydPayload,
        public bool $replayed,
    ) {}
}
