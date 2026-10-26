<?php

declare(strict_types=1);

namespace Eventor\Content\Application\EventPublisher;

use Eventor\Content\Contract\Event\CollectionPublished;
use Eventor\Content\Contract\Event\PhotoUploaded;
use Eventor\Content\Domain\Collection\CollectionRepository;
use Eventor\Content\Domain\Event\CollectionChanged;
use Eventor\Content\Domain\Event\PhotoAdded;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Domain events of Content -> integration events (chapter 06, outbox): a published collection and an uploaded photo.
 */
final readonly class PublishContentEvents
{
    public function __construct(
        #[Target('event.bus')]
        private MessageBusInterface $eventBus,
        private CollectionRepository $collections,
        private ClockInterface $clock,
    ) {}

    #[AsMessageHandler(bus: 'event.bus')]
    public function onCollectionChanged(CollectionChanged $event): void
    {
        if ('published' !== $event->action) {
            return;
        }
        $collection = $this->collections->find($event->collectionId);
        \assert(null !== $collection);

        $this->eventBus->dispatch(
            new CollectionPublished($event->organizerId, $event->collectionId, $event->eventId, $collection->visibility(), $this->clock->now()),
            [TenantStamp::of($event->organizerId)],
        );
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onPhotoAdded(PhotoAdded $event): void
    {
        $this->eventBus->dispatch(
            new PhotoUploaded($event->organizerId, $event->photoId, $event->collectionId, $event->storageKey, $this->clock->now()),
            [TenantStamp::of($event->organizerId)],
        );
    }
}
