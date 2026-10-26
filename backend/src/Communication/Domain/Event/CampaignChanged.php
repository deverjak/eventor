<?php

declare(strict_types=1);

namespace Eventor\Communication\Domain\Event;

use Eventor\Communication\Contract\CampaignState;
use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\CampaignId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Domain event: a campaign was drafted, edited, scheduled, started or cancelled. Audited.
 */
final readonly class CampaignChanged implements Auditable
{
    public function __construct(
        public OrganizerId $organizerId,
        public CampaignId $campaignId,
        public string $change,
        public CampaignState $state,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange('campaign', $this->campaignId->toString(), 'communication.campaign_'.$this->change, $this->organizerId, after: ['state' => $this->state->value]);
    }
}
