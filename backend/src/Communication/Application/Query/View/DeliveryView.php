<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Query\View;

/**
 * One row of the delivery history (COM-06): state, time, error, and a masked hint of the recipient (never the address).
 */
final readonly class DeliveryView
{
    public function __construct(
        public string $id,
        public ?string $campaignId,
        public string $trigger,
        public string $templateKey,
        public string $purpose,
        public string $state,
        public int $attempts,
        public ?string $detail,
        public ?string $recipientHint,
        public ?string $orderId,
        public ?string $activityId,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $sendAfter,
        public ?\DateTimeImmutable $sentAt,
    ) {}
}
