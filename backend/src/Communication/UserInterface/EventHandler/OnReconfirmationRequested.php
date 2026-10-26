<?php

declare(strict_types=1);

namespace Eventor\Communication\UserInterface\EventHandler;

use Eventor\Communication\Application\Port\ParticipationFacts;
use Eventor\Communication\Application\Scheduling\ScheduleDelivery;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Legal\Contract\Event\ReconfirmationRequested;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * S15, LEG-08: everybody who has to confirm a new version of a document is asked once. The orders come from the event
 * and from the orders of the participations it names.
 */
#[AsMessageHandler(fromTransport: 'communication_events')]
final readonly class OnReconfirmationRequested
{
    public function __construct(
        private CommandBus $commands,
        private ParticipationFacts $participations,
    ) {}

    public function __invoke(ReconfirmationRequested $event): void
    {
        $orders = [];
        foreach ($event->orderIds as $order) {
            $orders[$order->toString()] = $order;
        }
        foreach ($this->participations->find($event->organizerId, $event->participationIds) as $participation) {
            $orders[$participation->orderId->toString()] = $participation->orderId;
        }

        $tenant = TenantStamp::of($event->organizerId);
        foreach ($orders as $order) {
            $this->commands->dispatch(new ScheduleDelivery(
                TemplateKey::ReconfirmationRequest,
                \sprintf('reconfirmation:%s:%s:%s', $event->requirementId->toString(), $event->versionId->toString(), $order->toString()),
                orderId: $order->toString(),
            ), $tenant);
        }
    }
}
