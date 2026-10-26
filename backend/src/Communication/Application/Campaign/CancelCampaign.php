<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Campaign;

final readonly class CancelCampaign
{
    public function __construct(public string $campaignId) {}
}
