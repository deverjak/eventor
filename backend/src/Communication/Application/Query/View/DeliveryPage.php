<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Query\View;

final readonly class DeliveryPage
{
    /**
     * @param list<DeliveryView> $items
     */
    public function __construct(
        public array $items,
        public int $total,
    ) {}
}
