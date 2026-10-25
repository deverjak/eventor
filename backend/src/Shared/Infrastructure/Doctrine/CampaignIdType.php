<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Eventor\Shared\Domain\Id\CampaignId;

final class CampaignIdType extends AbstractIdType
{
    public const string NAME = 'campaign_id';

    protected function idClass(): string
    {
        return CampaignId::class;
    }
}
