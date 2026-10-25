<?php

declare(strict_types=1);

namespace Eventor\Payments\Contract\Event;

use Eventor\Payments\Contract\RefundReason;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\RefundTaskId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Money;

/**
 * A manual refund task was opened (PAY-10). Eventor never moves the money itself.
 *
 * P0. Planned subscribers: attention.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'payments.refund_task_opened', version: 1)]
final readonly class RefundTaskOpened
{
    public function __construct(
        public OrganizerId $organizerId,
        public RefundTaskId $refundTaskId,
        public OrderId $orderId,
        public Money $amount,
        public RefundReason $reason,
        public \DateTimeImmutable $occurredAt,
    ) {
        if (!$amount->isPositive()) {
            throw new \InvalidArgumentException('A refund task is for a positive amount.');
        }
    }
}
