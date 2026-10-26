<?php

declare(strict_types=1);

namespace Eventor\Communication\UserInterface\EventHandler;

use Eventor\Communication\Application\Scheduling\ScheduleDelivery;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Registration\Contract\Event\OrderPlaced;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Eventor\Shared\Domain\Payment\PaymentMethod;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * REG-07: the confirmation of a registration. An order paid by bank transfer is confirmed by the message with the
 * payment details (`OnPaymentRequestIssued`, which also carries the QR code), so the participant gets one message,
 * not two; cash and free orders are confirmed here.
 */
#[AsMessageHandler(fromTransport: 'communication_events')]
final readonly class OnOrderPlaced
{
    public function __construct(private CommandBus $commands) {}

    public function __invoke(OrderPlaced $event): void
    {
        if (PaymentMethod::BankTransfer === $event->paymentMethod && $event->amountDue->minor > 0) {
            return;
        }

        $this->commands->dispatch(new ScheduleDelivery(
            TemplateKey::OrderReceived,
            'order_received:'.$event->orderId->toString(),
            eventId: $event->eventId->toString(),
            orderId: $event->orderId->toString(),
        ), TenantStamp::of($event->organizerId));
    }
}
