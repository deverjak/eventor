<?php

declare(strict_types=1);

namespace Eventor\Communication\Contract\Event;

use Eventor\Shared\Domain\Id\CampaignId;
use Eventor\Shared\Domain\Id\DeliveryId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;

/**
 * A delivery failed for good (retries exhausted, hard bounce). `trigger` is the stable key of the cause (`payment_instructions`, `campaign`...), `error` a code without the recipient address.
 *
 * P0. Planned subscribers: attention.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'communication.delivery_failed', version: 1)]
#[PendingSubscribers('attention')]
final readonly class DeliveryFailed
{
    public function __construct(
        public OrganizerId $organizerId,
        public DeliveryId $deliveryId,
        public ?CampaignId $campaignId,
        public string $trigger,
        public string $error,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
