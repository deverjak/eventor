<?php

declare(strict_types=1);

namespace Eventor\Content\Application\UpdateCollection;

use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('Repeating a state the collection already has (publishing a published one) changes and records nothing.')]
final readonly class UpdateCollectionHandler
{
    public function __construct(private CollectionOfEvent $collections, private ClockInterface $clock) {}

    /**
     * @return array{collectionId: string, published: bool}
     */
    public function __invoke(UpdateCollection $command): array
    {
        $collection = $this->collections->get($command->eventId, $command->collectionId);
        if (true === $command->published) {
            $collection->publish(\DateTimeImmutable::createFromInterface($this->clock->now()));
        } elseif (false === $command->published) {
            $collection->unpublish();
        }
        if (null !== $command->inArchive) {
            $collection->designateForArchive($command->inArchive);
        }

        return ['collectionId' => $collection->id()->toString(), 'published' => $collection->isPublished()];
    }
}
