<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Structure;

use Eventor\Catalog\Domain\Structure\ActivityRepository;
use Eventor\Catalog\Domain\Structure\EventRepository;
use Eventor\Catalog\Domain\Structure\Variant;
use Eventor\Catalog\Domain\Structure\VariantRepository;
use Eventor\Registration\Contract\CapacityBucketSpec;
use Eventor\Registration\Contract\CapacityProvisioner;
use Eventor\Shared\Domain\Capacity\CapacityScope;
use Eventor\Shared\Domain\Id\VariantId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * EVT-06: adds a variant (a price level) to an activity. In a published event the price list gets a new version and
 * the capacity bucket is created at once.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class AddVariantHandler
{
    public function __construct(
        private EventRepository $events,
        private ActivityRepository $activities,
        private VariantRepository $variants,
        private CapacityProvisioner $capacity,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{variantId: string}
     */
    public function __invoke(AddVariant $command): array
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $event = $this->events->get($command->eventId);
        $activity = $this->activities->get($event->id(), $command->activityId);

        $variant = Variant::create(
            VariantId::generate(),
            $event->organizerId(),
            $event->id(),
            $activity->id(),
            $command->input->toDetails(),
            $this->variants->nextPosition($activity->id()),
            $now,
            $command->input->capacityLimit,
        );
        $this->variants->add($variant);

        if ($event->isLive()) {
            $this->capacity->provision($event->organizerId(), $event->id(), [
                new CapacityBucketSpec($variant->capacityBucketId(), CapacityScope::Variant, $variant->id()->toString(), $variant->capacityLimit(), $variant->capacityUnit()),
            ]);
            $event->bumpPriceListVersion($now);
        }

        return ['variantId' => $variant->id()->toString()];
    }
}
