<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Reminder;

/**
 * PAY-09: schedules the reminders of a payment request according to the organizer's schedule. Whether one is still
 * wanted is decided when it falls due (`RecipientStillEligible`), never by cancelling rows.
 */
final readonly class PlanPaymentReminders
{
    public function __construct(
        public string $orderId,
        public string $paymentRequestId,
        public \DateTimeImmutable $dueAt,
    ) {}
}
