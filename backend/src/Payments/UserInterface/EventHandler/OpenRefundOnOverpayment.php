<?php

declare(strict_types=1);

namespace Eventor\Payments\UserInterface\EventHandler;

use Eventor\Payments\Application\Refund\OpenRefundForOverpayment;
use Eventor\Payments\Contract\Event\OverpaymentDetected;
use Eventor\Shared\Application\Messaging\CommandBus;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * `payments.overpayment_detected` -> `payments.open_refund_task` (according to the organizer policy, S07).
 */
#[AsMessageHandler(fromTransport: 'payments_events')]
final readonly class OpenRefundOnOverpayment
{
    public function __construct(private CommandBus $commands) {}

    public function __invoke(OverpaymentDetected $event): void
    {
        $this->commands->dispatch(new OpenRefundForOverpayment($event->orderId->toString()));
    }
}
