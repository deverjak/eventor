<?php

declare(strict_types=1);

namespace Eventor\Registration\UserInterface\EventHandler;

use Eventor\Payments\Contract\Event\OrderPaymentChanged;
use Eventor\Registration\Application\Payment\ApplyOrderPayment;
use Eventor\Shared\Application\Audit\AuditContext;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Eventor\Shared\Domain\Audit\AuditActor;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * `payments.order_payment_changed` (chapter 04, path 4): confirm on paid, keep the reservation on a partial payment,
 * deal with late payments (S05, S06).
 */
#[AsMessageHandler(fromTransport: 'registration_events')]
final readonly class OnOrderPaymentChanged
{
    public function __construct(
        private CommandBus $commands,
        private AuditContext $audit,
    ) {}

    public function __invoke(OrderPaymentChanged $event): void
    {
        $this->audit->as(AuditActor::system('registration.confirm_participations'), fn(): mixed => $this->commands->dispatch(
            new ApplyOrderPayment($event->organizerId, $event->orderId, $event->newState, $event->delta->isPositive()),
            TenantStamp::of($event->organizerId),
        ));
    }
}
