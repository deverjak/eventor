<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Query\View;

final readonly class CampaignPage
{
    /**
     * @param list<CampaignView> $items
     */
    public function __construct(
        public array $items,
        public int $total,
    ) {}
}
