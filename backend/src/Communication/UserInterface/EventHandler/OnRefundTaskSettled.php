<?php

declare(strict_types=1);

namespace Eventor\Communication\UserInterface\EventHandler;

use Eventor\Communication\Application\Scheduling\ScheduleDelivery;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Payments\Contract\Event\RefundTaskSettled;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * PAY-11: the participant is told that the money was returned.
 */
#[AsMessageHandler(fromTransport: 'communication_events')]
final readonly class OnRefundTaskSettled
{
    public function __construct(private CommandBus $commands) {}

    public function __invoke(RefundTaskSettled $event): void
    {
        $this->commands->dispatch(new ScheduleDelivery(
            TemplateKey::RefundSettled,
            'refund_settled:'.$event->refundTaskId->toString(),
            orderId: $event->orderId->toString(),
            params: ['amountMinor' => $event->amount->minor, 'currency' => $event->amount->currency->value],
        ), TenantStamp::of($event->organizerId));
    }
}
