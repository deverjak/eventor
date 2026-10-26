<?php

declare(strict_types=1);

namespace Eventor\Communication\Domain\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

/**
 * The campaign is not in a state where the action is possible (a sent campaign cannot be edited, a draft cannot be
 * completed...).
 */
#[AsProblem(code: 'campaign_state_invalid', status: 409)]
final class CampaignStateInvalid extends DomainException
{
    public static function for(string $action, string $state): self
    {
        return new self(\sprintf('A campaign in state "%s" cannot be "%s".', $state, $action));
    }
}
