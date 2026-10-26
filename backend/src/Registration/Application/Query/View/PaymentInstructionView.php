<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Query\View;

use Eventor\Shared\Domain\Money;

/**
 * Payment instructions as the self-service page shows them (PAY-03). `spaydPayload` is Pairrot's frozen SPAYD: the
 * frontend renders it as the QR code and never rebuilds it.
 */
final readonly class PaymentInstructionView
{
    public function __construct(
        public string $variableSymbol,
        public string $accountNumber,
        public ?string $iban,
        public Money $amount,
        public \DateTimeImmutable $dueAt,
        public string $spaydPayload,
        public string $message,
    ) {}
}
