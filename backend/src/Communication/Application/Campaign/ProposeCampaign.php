<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Campaign;

use Eventor\Communication\Contract\CampaignAudienceSpec;
use Eventor\Communication\Contract\TemplateKey;

/**
 * A draft campaign the platform proposes ("notify those affected", chapter 04 path 10). `proposalKey` makes a
 * system reaction that is delivered twice propose it once.
 */
final readonly class ProposeCampaign
{
    public function __construct(
        public string $eventId,
        public TemplateKey $templateKey,
        public CampaignAudienceSpec $audience,
        public ?string $proposalKey = null,
        public ?string $refId = null,
    ) {}
}
