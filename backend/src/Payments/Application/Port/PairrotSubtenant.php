<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Port;

final readonly class PairrotSubtenant
{
    public function __construct(
        public string $id,
        public string $externalId,
    ) {}
}
