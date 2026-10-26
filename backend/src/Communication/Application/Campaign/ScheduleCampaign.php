<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Campaign;

final readonly class ScheduleCampaign
{
    public function __construct(
        public string $campaignId,
        public \DateTimeImmutable $at,
    ) {}
}
