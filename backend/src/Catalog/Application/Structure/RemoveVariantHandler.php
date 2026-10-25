<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Structure;

use Eventor\Catalog\Domain\Exception\EventNotDraft;
use Eventor\Catalog\Domain\Structure\EventRepository;
use Eventor\Catalog\Domain\Structure\VariantRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Removes a variant of a draft event (see RemoveActivityHandler for why only a draft).
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class RemoveVariantHandler
{
    public function __construct(
        private EventRepository $events,
        private VariantRepository $variants,
    ) {}

    public function __invoke(RemoveVariant $command): void
    {
        $event = $this->events->get($command->eventId);
        if (!$event->isDraft()) {
            throw EventNotDraft::for('Removing a variant');
        }
        $variant = $this->variants->get($event->id(), $command->variantId);
        $event->noteVariantRemoved($variant->activityId(), $variant->id());
        $this->variants->remove($variant);
    }
}
