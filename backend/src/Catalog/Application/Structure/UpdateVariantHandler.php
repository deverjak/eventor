<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Structure;

use Eventor\Catalog\Domain\Structure\EventRepository;
use Eventor\Catalog\Domain\Structure\VariantRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * EVT-06: replaces the data of a variant. A price change in a published event gives the price list a new version, so
 * orders in flight keep the price they were made with.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class UpdateVariantHandler
{
    public function __construct(
        private EventRepository $events,
        private VariantRepository $variants,
        private ClockInterface $clock,
    ) {}

    public function __invoke(UpdateVariant $command): void
    {
        $event = $this->events->get($command->eventId);
        $variant = $this->variants->get($event->id(), $command->variantId);
        $variant->assertVersion($command->expectedVersion);
        $changed = $variant->change($command->input->toDetails());
        if ($event->isLive() && \in_array('price', $changed, true)) {
            $event->bumpPriceListVersion(\DateTimeImmutable::createFromInterface($this->clock->now()));
        }
    }
}
