<?php

declare(strict_types=1);

namespace Eventor\Communication\UserInterface\EventHandler;

use Eventor\Communication\Application\Scheduling\ScheduleDelivery;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Registration\Contract\Event\ParticipationVariantChanged;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * P1, "změna registrace": the orderer is told what the participation is now.
 */
#[AsMessageHandler(fromTransport: 'communication_events')]
final readonly class OnParticipationVariantChanged
{
    public function __construct(private CommandBus $commands) {}

    public function __invoke(ParticipationVariantChanged $event): void
    {
        $this->commands->dispatch(new ScheduleDelivery(
            TemplateKey::ParticipationChanged,
            \sprintf('participation_changed:%s:%s', $event->participationId->toString(), $event->newVariantId->toString()),
            orderId: $event->orderId->toString(),
            activityId: $event->newActivityId->toString(),
            refId: $event->participationId->toString(),
        ), TenantStamp::of($event->organizerId));
    }
}
