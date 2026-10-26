<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Campaign;

use Eventor\Communication\Contract\CampaignAudienceSpec;
use Eventor\Communication\Contract\CampaignPurpose;
use Eventor\Communication\Contract\MessageLocale;
use Eventor\Communication\Contract\TemplateKey;

final readonly class UpdateCampaign
{
    public function __construct(
        public string $campaignId,
        public string $name,
        public CampaignPurpose $purpose,
        public MessageLocale $locale,
        public ?TemplateKey $templateKey,
        public ?string $subject,
        public ?string $body,
        public CampaignAudienceSpec $audience,
    ) {}
}
