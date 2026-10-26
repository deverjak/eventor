<?php

declare(strict_types=1);

namespace Eventor\Communication\UserInterface\EventHandler;

use Eventor\Communication\Application\Reminder\PlanPreEventReminders;
use Eventor\Communication\Application\Scheduling\ScheduleDelivery;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Registration\Contract\Event\ParticipationsConfirmed;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Eventor\Shared\Domain\Id\ParticipationId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * COM-01: "platba potvrzena / účast potvrzena", and the pre-event reminders of the confirmed activities.
 */
#[AsMessageHandler(fromTransport: 'communication_events')]
final readonly class OnParticipationsConfirmed
{
    public function __construct(private CommandBus $commands) {}

    public function __invoke(ParticipationsConfirmed $event): void
    {
        $tenant = TenantStamp::of($event->organizerId);
        $ids = array_map(static fn(ParticipationId $id): string => $id->toString(), $event->participationIds);
        sort($ids);

        $this->commands->dispatch(new ScheduleDelivery(
            TemplateKey::ParticipationConfirmed,
            \sprintf('participations_confirmed:%s:%s', $event->orderId->toString(), md5(implode(',', $ids))),
            orderId: $event->orderId->toString(),
            params: ['reason' => $event->reason->value],
        ), $tenant);

        $this->commands->dispatch(new PlanPreEventReminders($event->orderId->toString(), $ids), $tenant);
    }
}
