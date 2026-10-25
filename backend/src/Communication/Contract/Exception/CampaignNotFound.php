<?php

declare(strict_types=1);

namespace Eventor\Communication\Contract\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Id\CampaignId;

/**
 * No such campaign.
 */
#[AsProblem(code: 'campaign_not_found', status: 404)]
final class CampaignNotFound extends DomainException
{
    public static function forId(CampaignId $id): self
    {
        return new self(\sprintf('Campaign "%s" was not found.', $id->toString()));
    }
}
