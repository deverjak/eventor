<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\EventPublisher;

use Eventor\Catalog\Contract\Event\FormVersionPublished;
use Eventor\Catalog\Domain\Event\FormVersionFrozen;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * A frozen form version -> `catalog.form_version_published` (FRM-08, S17).
 */
#[AsMessageHandler(bus: 'event.bus')]
final readonly class PublishFormVersionPublished
{
    public function __construct(
        private MessageBusInterface $eventBus,
        private ClockInterface $clock,
    ) {}

    public function __invoke(FormVersionFrozen $event): void
    {
        $this->eventBus->dispatch(
            new FormVersionPublished($event->organizerId, $event->eventId, $event->formVersionId, $event->previousFormVersionId, $event->addedRequiredFieldKeys, \DateTimeImmutable::createFromInterface($this->clock->now())),
            [TenantStamp::of($event->organizerId)],
        );
    }
}
