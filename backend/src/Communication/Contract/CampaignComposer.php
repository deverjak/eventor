<?php

declare(strict_types=1);

namespace Eventor\Communication\Contract;

use Eventor\Shared\Domain\Id\CampaignId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Entry point for other modules that want to message participants (chapter 04, path 10): preview how many people a
 * change affects, and propose a campaign draft. Sending is always the organizer's decision in Communication's own API.
 * Contacts and consent are resolved inside Communication at send time.
 */
interface CampaignComposer
{
    public function preview(OrganizerId $organizer, CampaignAudience $audience): CampaignPreview;

    /**
     * Creates a draft transactional campaign (for example "activity changed") and returns it for confirmation.
     */
    public function propose(OrganizerId $organizer, ProposedCampaign $campaign): CampaignId;
}
