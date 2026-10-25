<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Port;

final readonly class PairrotBankConnection
{
    public function __construct(
        public string $id,
        public string $iban,
        public ?string $inboundAddress,
    ) {}
}
