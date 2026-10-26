<?php

declare(strict_types=1);

namespace Eventor\Content\Domain\Collection;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Content\Domain\Event\PhotoAdded;
use Eventor\Shared\Domain\Event\RecordsEvents;
use Eventor\Shared\Domain\Event\RecordsEventsInterface;
use Eventor\Shared\Domain\Id\CollectionId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\PhotoId;
use Eventor\Shared\Domain\Tenant\TenantScoped;

/**
 * A photo of an internal gallery (POST-01): the original in object storage, a thumbnail generated asynchronously,
 * the metadata the organizer typed in (description, author, day or part of the event) and its place in the gallery.
 */
#[ORM\Entity]
#[ORM\Table(name: 'photo', schema: 'content')]
class Photo implements TenantScoped, RecordsEventsInterface
{
    use RecordsEvents;

    #[ORM\Column(type: 'string', length: 200, nullable: true)]
    private ?string $thumbnailKey = null;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'photo_id')]
        private PhotoId $id,
        #[ORM\Column(type: 'organizer_id')]
        private OrganizerId $organizerId,
        #[ORM\Column(type: 'collection_id')]
        private CollectionId $collectionId,
        #[ORM\Column(type: 'string', length: 200)]
        private string $storageKey,
        #[ORM\Column(type: 'string', length: 40)]
        private string $contentType,
        #[ORM\Column(type: 'string', length: 1000, nullable: true)]
        private ?string $description,
        #[ORM\Column(type: 'string', length: 200, nullable: true)]
        private ?string $author,
        #[ORM\Column(type: 'string', length: 200, nullable: true)]
        private ?string $eventPart,
        #[ORM\Column(type: 'integer')]
        private int $position,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $createdAt,
    ) {}

    public static function add(PhotoId $id, Collection $collection, string $storageKey, string $contentType, ?string $description, ?string $author, ?string $eventPart, int $position, \DateTimeImmutable $now): self
    {
        $collection->assertAcceptsPhotos();
        $photo = new self($id, $collection->organizerId(), $collection->id(), $storageKey, $contentType, $description, $author, $eventPart, $position, $now);
        $photo->record(new PhotoAdded($collection->organizerId(), $id, $collection->id(), $storageKey, $now));

        return $photo;
    }

    public function attachThumbnail(string $key): void
    {
        $this->thumbnailKey = $key;
    }

    public function id(): PhotoId
    {
        return $this->id;
    }

    public function organizerId(): OrganizerId
    {
        return $this->organizerId;
    }

    public function storageKey(): string
    {
        return $this->storageKey;
    }

    public function thumbnailKey(): ?string
    {
        return $this->thumbnailKey;
    }
}
