<?php

declare(strict_types=1);

namespace Eventor\Communication\UserInterface\Http\Request;

use Eventor\Communication\Contract\CampaignAudienceSpec;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * COM-04 targeting. Empty lists mean "no restriction".
 */
final readonly class AudienceRequest
{
    /**
     * @param list<string> $activityIds
     * @param list<string> $variantIds
     * @param list<string> $participationStates pending, confirmed, expired, requires_attention, cancelled, transferred
     * @param list<string> $financialStates unpaid, partially_paid, paid, overpaid, ...
     */
    public function __construct(
        #[Assert\All(constraints: [new Assert\Uuid()])]
        public array $activityIds = [],
        #[Assert\All(constraints: [new Assert\Uuid()])]
        public array $variantIds = [],
        #[Assert\All(constraints: [new Assert\Choice(choices: ['pending', 'confirmed', 'expired', 'requires_attention', 'cancelled', 'transferred'])])]
        public array $participationStates = [],
        #[Assert\All(constraints: [new Assert\Choice(choices: ['unpaid', 'partially_paid', 'paid', 'overpaid', 'disputed', 'refund_pending', 'refunded', 'not_required'])])]
        public array $financialStates = [],
        public ?bool $checkedIn = null,
    ) {}

    public function spec(): CampaignAudienceSpec
    {
        return new CampaignAudienceSpec($this->activityIds, $this->variantIds, $this->participationStates, $this->financialStates, $this->checkedIn);
    }
}
