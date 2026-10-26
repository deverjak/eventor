<?php

declare(strict_types=1);

namespace Eventor\Communication\UserInterface\EventHandler;

use Eventor\Communication\Application\Scheduling\ScheduleDelivery;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Payments\Contract\Event\OrderPaymentChanged;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Eventor\Shared\Domain\Finance\FinancialState;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * COM-01, "částečná platba": a partial payment is acknowledged with what is still missing. A complete payment is
 * acknowledged by the confirmation of the participation (`OnParticipationsConfirmed`), so there is one message, not two.
 */
#[AsMessageHandler(fromTransport: 'communication_events')]
final readonly class OnOrderPaymentChanged
{
    public function __construct(private CommandBus $commands) {}

    public function __invoke(OrderPaymentChanged $event): void
    {
        if (FinancialState::PartiallyPaid !== $event->newState || FinancialState::PartiallyPaid === $event->previousState && $event->delta->minor <= 0) {
            return;
        }

        $this->commands->dispatch(new ScheduleDelivery(
            TemplateKey::PaymentPartial,
            \sprintf('payment_partial:%s:%d', $event->orderId->toString(), $event->ledgerVersion),
            orderId: $event->orderId->toString(),
        ), TenantStamp::of($event->organizerId));
    }
}
