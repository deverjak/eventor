<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Structure;

use Eventor\Catalog\Domain\Exception\InvalidCatalogData;
use Eventor\Catalog\Domain\Structure\ActivityRepository;
use Eventor\Catalog\Domain\Structure\Event;
use Eventor\Catalog\Domain\Structure\EventRepository;
use Eventor\Catalog\Domain\Structure\VariantRepository;
use Eventor\Registration\Contract\CapacityProvisioner;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\VariantId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * CAP-06 (chapter 04, path 1): sets the configured limit of the event, an activity or a variant. In a published
 * event Registration changes the bucket in this transaction without checking the occupancy (ADR 002) and tells
 * whether the new limit is below it; Catalog then emits `catalog.capacity_changed`. Nothing is cancelled.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class ChangeCapacityHandler
{
    public function __construct(
        private EventRepository $events,
        private ActivityRepository $activities,
        private VariantRepository $variants,
        private CapacityProvisioner $capacity,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{belowOccupancy: bool, warnings: list<string>}
     */
    public function __invoke(ChangeCapacity $command): array
    {
        $input = $command->input;
        $event = $this->events->get($command->eventId);
        $live = $event->isLive();
        $reason = null === $input->reason || '' === trim($input->reason) ? null : trim($input->reason);
        if ($live && null === $reason) {
            throw InvalidCatalogData::because('A reason is required to change the capacity of a published event.');
        }
        if ('event' !== $input->scope && null === $input->scopeId) {
            throw InvalidCatalogData::because('The scopeId of the activity or variant is required.');
        }

        $target = match ($input->scope) {
            'activity' => $this->activities->get($event->id(), ActivityId::fromString((string) $input->scopeId)),
            'variant' => $this->variants->get($event->id(), VariantId::fromString((string) $input->scopeId)),
            default => $event,
        };

        $below = false;
        if ($live) {
            $below = $this->capacity->changeLimit($event->organizerId(), $target->capacityBucketId(), $input->limit, (string) $reason)->belowOccupancy;
        }

        if ($target instanceof Event) {
            $target->changeCapacityLimit($input->limit, $below, $reason, \DateTimeImmutable::createFromInterface($this->clock->now()));
        } else {
            $target->changeCapacityLimit($input->limit, $below, $reason, $live);
        }

        return ['belowOccupancy' => $below, 'warnings' => $below ? ['capacity_below_occupancy'] : []];
    }
}
