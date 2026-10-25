<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Structure;

use Eventor\Catalog\Domain\Structure\Activity;
use Eventor\Catalog\Domain\Structure\ActivityRepository;
use Eventor\Catalog\Domain\Structure\EventRepository;
use Eventor\Registration\Contract\CapacityBucketSpec;
use Eventor\Registration\Contract\CapacityProvisioner;
use Eventor\Shared\Domain\Capacity\CapacityScope;
use Eventor\Shared\Domain\Capacity\CapacityUnit;
use Eventor\Shared\Domain\Id\ActivityId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * EVT-05: adds an activity. In a published event its capacity bucket is created at once (Registration, same
 * transaction), so the activity is bookable as soon as its registration is opened.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class AddActivityHandler
{
    public function __construct(
        private EventRepository $events,
        private ActivityRepository $activities,
        private CapacityProvisioner $capacity,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{activityId: string}
     */
    public function __invoke(AddActivity $command): array
    {
        $event = $this->events->get($command->eventId);
        $activity = Activity::create(
            ActivityId::generate(),
            $event->organizerId(),
            $event->id(),
            $command->input->toDetails(),
            false,
            $this->activities->nextPosition($event->id()),
            \DateTimeImmutable::createFromInterface($this->clock->now()),
            $command->input->capacityLimit,
        );
        $this->activities->add($activity);

        if ($event->isLive()) {
            $this->capacity->provision($event->organizerId(), $event->id(), [
                new CapacityBucketSpec($activity->capacityBucketId(), CapacityScope::Activity, $activity->id()->toString(), $activity->capacityLimit(), CapacityUnit::Seat),
            ]);
        }

        return ['activityId' => $activity->id()->toString()];
    }
}
