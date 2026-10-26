<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Scheduling;

use Eventor\Communication\Domain\Delivery\Delivery;
use Eventor\Communication\Domain\Delivery\DeliveryRepository;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Eventor\Shared\Application\Tenant\TenantContext;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\CampaignId;
use Eventor\Shared\Domain\Id\DeliveryId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrderId;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Creates a `Delivery` once per idempotency key and queues its first attempt. A delivery that is not due yet (a
 * reminder) waits for the sweeper (`DispatchDueDeliveries`); everything else is queued in the same transaction (the
 * outbox: the row and the queue message commit together).
 */
final readonly class DeliveryScheduler
{
    public function __construct(
        private DeliveryRepository $deliveries,
        private TenantContext $tenant,
        private ClockInterface $clock,
        #[Target('event.bus')]
        private MessageBusInterface $bus,
    ) {}

    /**
     * @return Delivery|null null when the key already exists (a redelivered event)
     */
    public function schedule(ScheduleDelivery $command, ?CampaignId $campaign = null): ?Delivery
    {
        if ($this->deliveries->existsWithKey($command->idempotencyKey)) {
            return null;
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $organizer = $this->tenant->require();
        $sendAfter = $command->sendAfter ?? $now;

        $delivery = Delivery::schedule(
            DeliveryId::generate(),
            $organizer,
            $campaign,
            null === $campaign ? $command->trigger->value : 'campaign',
            $command->trigger,
            $command->purpose,
            null === $command->eventId ? null : EventId::fromString($command->eventId),
            null === $command->orderId ? null : OrderId::fromString($command->orderId),
            null === $command->activityId ? null : ActivityId::fromString($command->activityId),
            $command->refId,
            $command->params,
            $command->idempotencyKey,
            $sendAfter,
            $now,
        );
        $this->deliveries->add($delivery);

        if ($sendAfter <= $now) {
            $delivery->markDispatched($now);
            $this->bus->dispatch(new SendDelivery($delivery->id()->toString(), $organizer->toString()), [TenantStamp::of($organizer)]);
        }

        return $delivery;
    }

    /**
     * Queues an attempt of an existing delivery (retry, sweeper).
     */
    public function queue(Delivery $delivery): void
    {
        $delivery->markDispatched(\DateTimeImmutable::createFromInterface($this->clock->now()));
        $this->bus->dispatch(new SendDelivery($delivery->id()->toString(), $delivery->organizerId()->toString()), [TenantStamp::of($delivery->organizerId())]);
    }
}
