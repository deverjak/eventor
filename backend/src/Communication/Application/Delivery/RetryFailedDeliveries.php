<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Delivery;

/**
 * COM-06: retry the failed deliveries of a campaign, or one failed delivery, without duplicates (same idempotency key).
 */
final readonly class RetryFailedDeliveries
{
    public function __construct(
        public ?string $campaignId = null,
        public ?string $deliveryId = null,
    ) {}
}
