<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Structure;

use Eventor\Catalog\Domain\Exception\EventNotDraft;
use Eventor\Catalog\Domain\Structure\ActivityRepository;
use Eventor\Catalog\Domain\Structure\EventRepository;
use Eventor\Catalog\Domain\Structure\VariantRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Removes an activity with its variants. Only a draft event may lose structure: once it is published, registrations
 * can depend on it, and the activity is closed instead (EVT-04).
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class RemoveActivityHandler
{
    public function __construct(
        private EventRepository $events,
        private ActivityRepository $activities,
        private VariantRepository $variants,
    ) {}

    public function __invoke(RemoveActivity $command): void
    {
        $event = $this->events->get($command->eventId);
        if (!$event->isDraft()) {
            throw EventNotDraft::for('Removing an activity');
        }
        $activity = $this->activities->get($event->id(), $command->activityId);
        foreach ($this->variants->forActivity($activity->id()) as $variant) {
            $event->noteVariantRemoved($activity->id(), $variant->id());
            $this->variants->remove($variant);
        }
        $event->noteActivityRemoved($activity->id());
        $this->activities->remove($activity);
    }
}
