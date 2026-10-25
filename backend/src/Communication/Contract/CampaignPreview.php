<?php

declare(strict_types=1);

namespace Eventor\Communication\Contract;

/**
 * How many deliveries the audience would produce right now (one per orderer and activity).
 */
final readonly class CampaignPreview
{
    public function __construct(
        public int $recipientCount,
    ) {}
}
