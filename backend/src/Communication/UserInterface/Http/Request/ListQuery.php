<?php

declare(strict_types=1);

namespace Eventor\Communication\UserInterface\Http\Request;

use Eventor\Communication\Contract\CampaignState;
use Eventor\Communication\Contract\DeliveryState;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Filters and paging of the campaign and delivery lists.
 */
final readonly class ListQuery
{
    public function __construct(
        #[Assert\Uuid]
        public ?string $eventId = null,
        #[Assert\Uuid]
        public ?string $campaignId = null,
        #[Assert\Uuid]
        public ?string $orderId = null,
        public ?CampaignState $campaignState = null,
        public ?DeliveryState $state = null,
        #[Assert\Positive]
        public int $page = 1,
        #[Assert\Range(min: 1, max: 100)]
        public int $perPage = 25,
    ) {}
}
