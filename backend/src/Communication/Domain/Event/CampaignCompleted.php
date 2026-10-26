<?php

declare(strict_types=1);

namespace Eventor\Communication\Domain\Event;

use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\CampaignId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Domain event: every delivery of the campaign left the scheduled state (COM-06). Becomes `communication.campaign_sent`.
 */
final readonly class CampaignCompleted implements Auditable
{
    public function __construct(
        public OrganizerId $organizerId,
        public CampaignId $campaignId,
        public int $deliveryCount,
        public int $skippedCount,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange('campaign', $this->campaignId->toString(), 'communication.campaign_completed', $this->organizerId, after: [
            'sent' => $this->deliveryCount,
            'skipped' => $this->skippedCount,
        ]);
    }
}
