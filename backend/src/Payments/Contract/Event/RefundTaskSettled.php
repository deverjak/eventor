<?php

declare(strict_types=1);

namespace Eventor\Payments\Contract\Event;

use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\RefundTaskId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;
use Eventor\Shared\Domain\Money;

/**
 * Finance settled a refund task (PAY-11); a `refund_settled` ledger entry was written.
 *
 * P0. Planned subscribers: attention, communication.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'payments.refund_task_settled', version: 1)]
#[PendingSubscribers('attention', 'communication')]
final readonly class RefundTaskSettled
{
    public function __construct(
        public OrganizerId $organizerId,
        public RefundTaskId $refundTaskId,
        public OrderId $orderId,
        public Money $amount,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
