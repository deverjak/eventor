<?php

declare(strict_types=1);

namespace Eventor\Content\Application\UpdateCollection;

use Eventor\Content\Contract\Exception\CollectionNotFound;
use Eventor\Content\Domain\Collection\Collection;
use Eventor\Content\Domain\Collection\CollectionRepository;
use Eventor\Shared\Domain\Id\CollectionId;

/**
 * The collection addressed by an admin URL `/events/{eventId}/collections/{collectionId}`: it must belong to that event
 * (the tenant filter already hides the rows of other organizers), otherwise it is "not found".
 */
final readonly class CollectionOfEvent
{
    public function __construct(private CollectionRepository $collections) {}

    /**
     * @throws CollectionNotFound
     */
    public function get(string $eventId, string $collectionId): Collection
    {
        $id = CollectionId::fromString($collectionId);
        $collection = $this->collections->find($id);
        if (null === $collection || $collection->eventId()->toString() !== $eventId) {
            throw CollectionNotFound::forId($id);
        }

        return $collection;
    }
}
