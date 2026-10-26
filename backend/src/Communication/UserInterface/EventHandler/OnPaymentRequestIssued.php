<?php

declare(strict_types=1);

namespace Eventor\Communication\UserInterface\EventHandler;

use Eventor\Communication\Application\Reminder\PlanPaymentReminders;
use Eventor\Communication\Application\Scheduling\ScheduleDelivery;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Payments\Contract\Event\PaymentRequestIssued;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * REG-07, PAY-03, PAY-09: the payment details (account, variable symbol, QR) go out as soon as Pairrot issued the
 * request, and the reminders of the organizer's schedule are planned for its due date.
 */
#[AsMessageHandler(fromTransport: 'communication_events')]
final readonly class OnPaymentRequestIssued
{
    public function __construct(private CommandBus $commands) {}

    public function __invoke(PaymentRequestIssued $event): void
    {
        $tenant = TenantStamp::of($event->organizerId);

        $this->commands->dispatch(new ScheduleDelivery(
            TemplateKey::PaymentInstructions,
            'payment_instructions:'.$event->paymentRequestId->toString(),
            orderId: $event->orderId->toString(),
            refId: $event->paymentRequestId->toString(),
        ), $tenant);

        $this->commands->dispatch(new PlanPaymentReminders($event->orderId->toString(), $event->paymentRequestId->toString(), \DateTimeImmutable::createFromInterface($event->dueAt)), $tenant);
    }
}
