<?php

declare(strict_types=1);

namespace Eventor\Communication\UserInterface\EventHandler;

use Eventor\Communication\Application\Scheduling\ScheduleDelivery;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Registration\Contract\Event\ParticipationExpired;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * COM-01, "expirace": one message per order, however many of its reservations expired together.
 */
#[AsMessageHandler(fromTransport: 'communication_events')]
final readonly class OnParticipationExpired
{
    public function __construct(private CommandBus $commands) {}

    public function __invoke(ParticipationExpired $event): void
    {
        $this->commands->dispatch(new ScheduleDelivery(
            TemplateKey::ReservationExpired,
            'reservation_expired:'.$event->orderId->toString(),
            orderId: $event->orderId->toString(),
        ), TenantStamp::of($event->organizerId));
    }
}
