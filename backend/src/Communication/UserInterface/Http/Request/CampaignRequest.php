<?php

declare(strict_types=1);

namespace Eventor\Communication\UserInterface\Http\Request;

use Eventor\Communication\Contract\CampaignPurpose;
use Eventor\Communication\Contract\MessageLocale;
use Eventor\Communication\Contract\TemplateKey;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CampaignRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $eventId,
        #[Assert\NotBlank]
        #[Assert\Length(max: 200)]
        public string $name,
        public CampaignPurpose $purpose = CampaignPurpose::Organizational,
        public MessageLocale $locale = MessageLocale::Czech,
        public ?TemplateKey $templateKey = null,
        #[Assert\Length(max: 300)]
        public ?string $subject = null,
        #[Assert\Length(max: 20000)]
        public ?string $body = null,
        #[Assert\Valid]
        public AudienceRequest $audience = new AudienceRequest(),
    ) {}
}
