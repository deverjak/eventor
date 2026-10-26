<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Audience;

use Eventor\Communication\Contract\CampaignAudienceSpec;
use Eventor\Communication\Domain\Exception\InvalidCampaign;
use Eventor\Registration\Contract\ParticipationState;
use Eventor\Shared\Domain\Finance\FinancialState;
use Eventor\Shared\Domain\Id\AbstractId;

/**
 * The targeting a campaign stores must be understood by every module that narrows it later.
 */
final class AudienceSpecGuard
{
    /**
     * @throws InvalidCampaign
     */
    public static function assertValid(CampaignAudienceSpec $spec): void
    {
        foreach ([...$spec->activityIds, ...$spec->variantIds] as $id) {
            if (!AbstractId::isValid($id)) {
                throw InvalidCampaign::because(\sprintf('"%s" is not a valid id in the audience.', $id));
            }
        }
        foreach ($spec->participationStates as $state) {
            if (null === ParticipationState::tryFrom($state)) {
                throw InvalidCampaign::because(\sprintf('"%s" is not a participation state.', $state));
            }
        }
        foreach ($spec->financialStates as $state) {
            if (null === FinancialState::tryFrom($state)) {
                throw InvalidCampaign::because(\sprintf('"%s" is not a financial state.', $state));
            }
        }
    }
}
