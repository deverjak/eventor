<?php

declare(strict_types=1);

namespace Eventor\Communication\Domain\Event;

use Eventor\Communication\Contract\DeliveryState;
use Eventor\Shared\Domain\Id\CampaignId;
use Eventor\Shared\Domain\Id\DeliveryId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Domain event: a delivery was scheduled, sent, skipped, had a failed attempt or was re-planned. Not audited: the
 * delivery row is itself the history of the message (COM-06) and a campaign would flood the audit trail.
 */
final readonly class DeliveryChanged
{
    public function __construct(
        public OrganizerId $organizerId,
        public DeliveryId $deliveryId,
        public ?CampaignId $campaignId,
        public string $change,
        public DeliveryState $state,
    ) {}
}
