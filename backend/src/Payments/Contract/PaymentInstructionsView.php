<?php

declare(strict_types=1);

namespace Eventor\Payments\Contract;

use Eventor\Shared\Domain\Id\PaymentRequestId;
use Eventor\Shared\Domain\Money;

/**
 * Payment instructions of one request. `spaydPayload` is Pairrot's frozen SPAYD: render it as the QR code, never rebuild it (chapter 07).
 * Contains the account of the organizer (not a person), `message` is the order number.
 */
final readonly class PaymentInstructionsView
{
    public function __construct(
        public PaymentRequestId $paymentRequestId,
        public PaymentRequestPurpose $purpose,
        public PaymentRequestStatus $status,
        public string $variableSymbol,
        public string $accountNumber,
        public ?string $iban,
        public Money $amount,
        public \DateTimeImmutable $dueAt,
        public string $spaydPayload,
        public string $message,
    ) {}
}
