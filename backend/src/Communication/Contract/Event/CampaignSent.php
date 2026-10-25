<?php

declare(strict_types=1);

namespace Eventor\Communication\Contract\Event;

use Eventor\Shared\Domain\Id\CampaignId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;

/**
 * All deliveries of a campaign left the scheduled state (COM-06).
 *
 * P0. Planned subscribers: reporting.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'communication.campaign_sent', version: 1)]
#[PendingSubscribers('reporting')]
final readonly class CampaignSent
{
    public function __construct(
        public OrganizerId $organizerId,
        public CampaignId $campaignId,
        public int $deliveryCount,
        public int $skippedCount,
        public \DateTimeImmutable $occurredAt,
    ) {
        if ($deliveryCount < 0 || $skippedCount < 0) {
            throw new \InvalidArgumentException('Counts cannot be negative.');
        }
    }
}
