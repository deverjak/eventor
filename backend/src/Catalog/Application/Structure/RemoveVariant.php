<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Structure;

use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\VariantId;

final readonly class RemoveVariant
{
    public function __construct(
        public EventId $eventId,
        public VariantId $variantId,
    ) {}
}
