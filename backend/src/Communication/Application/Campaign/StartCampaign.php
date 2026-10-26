<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Campaign;

/**
 * Sends a campaign now: it breaks down into one delivery per orderer and activity. `tolerateStarted` lets a subscriber
 * that is delivered twice start a system campaign only once.
 */
final readonly class StartCampaign
{
    public function __construct(
        public string $campaignId,
        public bool $tolerateStarted = false,
    ) {}
}
