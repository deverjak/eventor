<?php

declare(strict_types=1);

namespace Eventor\Communication\Domain\Event;

use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\CampaignId;
use Eventor\Shared\Domain\Id\DeliveryId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Domain event: the retries are exhausted. Becomes `communication.delivery_failed` (Attention).
 */
final readonly class DeliveryFailedPermanently implements Auditable
{
    public function __construct(
        public OrganizerId $organizerId,
        public DeliveryId $deliveryId,
        public ?CampaignId $campaignId,
        public string $trigger,
        public string $error,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange('delivery', $this->deliveryId->toString(), 'communication.delivery_failed', $this->organizerId, after: ['trigger' => $this->trigger, 'error' => $this->error]);
    }
}
