<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Query\View;

/**
 * The Pairrot mapping of an organizer: ids and a masked IBAN, never keys.
 */
final readonly class PairrotConnectionView
{
    public function __construct(
        public string $subtenantId,
        public ?string $bankConnectionId,
        public ?string $maskedIban,
        public ?string $inboundAddress,
        public ?string $updatedAt,
    ) {}
}
