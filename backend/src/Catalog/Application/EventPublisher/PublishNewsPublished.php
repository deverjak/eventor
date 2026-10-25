<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\EventPublisher;

use Eventor\Catalog\Contract\Event\NewsPublished;
use Eventor\Catalog\Domain\Event\NewsPostModified;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * A published news post -> `catalog.news_published` (PRG-04); `broadcast` asks Communication to send it by e-mail.
 */
#[AsMessageHandler(bus: 'event.bus')]
final readonly class PublishNewsPublished
{
    public function __construct(
        private MessageBusInterface $eventBus,
        private ClockInterface $clock,
    ) {}

    public function __invoke(NewsPostModified $event): void
    {
        if ('published' !== $event->action) {
            return;
        }
        $this->eventBus->dispatch(
            new NewsPublished($event->organizerId, $event->eventId, $event->newsPostId, $event->broadcast, \DateTimeImmutable::createFromInterface($this->clock->now())),
            [TenantStamp::of($event->organizerId)],
        );
    }
}
