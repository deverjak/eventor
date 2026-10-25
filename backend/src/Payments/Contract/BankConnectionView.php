<?php

declare(strict_types=1);

namespace Eventor\Payments\Contract;

/**
 * A connected bank account. The account number is shown masked.
 */
final readonly class BankConnectionView
{
    public function __construct(
        public string $bankConnectionId,
        public string $maskedIban,
        public ?string $inboundAddress,
    ) {}
}
