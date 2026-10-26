<?php

declare(strict_types=1);

namespace Eventor\Communication\Domain\Campaign;

use Eventor\Shared\Domain\Id\CampaignId;

interface CampaignRepository
{
    public function add(Campaign $campaign): void;

    /** The campaign of the current organizer (the tenant filter scopes it), or null. */
    public function find(CampaignId $id): ?Campaign;

    /** Same, with a row lock for the duration of the transaction. */
    public function findForUpdate(CampaignId $id): ?Campaign;

    /** A campaign proposed by a system reaction (one per cause), or null. */
    public function findByProposalKey(string $proposalKey): ?Campaign;
}
