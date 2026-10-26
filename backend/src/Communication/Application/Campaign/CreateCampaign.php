<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Campaign;

use Eventor\Communication\Contract\CampaignAudienceSpec;
use Eventor\Communication\Contract\CampaignPurpose;
use Eventor\Communication\Contract\MessageLocale;
use Eventor\Communication\Contract\TemplateKey;

/**
 * Creates a campaign as a draft (COM-03). Without its own `subject` and `body` the wording of `templateKey` is used.
 */
final readonly class CreateCampaign
{
    public function __construct(
        public string $eventId,
        public string $name,
        public CampaignPurpose $purpose,
        public MessageLocale $locale,
        public ?TemplateKey $templateKey,
        public ?string $subject,
        public ?string $body,
        public CampaignAudienceSpec $audience,
    ) {}
}
