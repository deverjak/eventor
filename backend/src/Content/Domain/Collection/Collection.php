<?php

declare(strict_types=1);

namespace Eventor\Content\Domain\Collection;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Content\Contract\CollectionVisibility;
use Eventor\Content\Domain\Event\CollectionChanged;
use Eventor\Content\Domain\Exception\CollectionIsExternal;
use Eventor\Shared\Domain\Event\RecordsEvents;
use Eventor\Shared\Domain\Event\RecordsEventsInterface;
use Eventor\Shared\Domain\Id\CollectionId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Tenant\TenantScoped;

/**
 * Photos of an event (POST-01) or a link to a photographer's gallery (POST-02). A draft until published; public or
 * reachable only through an unguessable link (POST-03); `inArchive` puts it into the public archive (POST-07).
 */
#[ORM\Entity]
#[ORM\Table(name: 'collection', schema: 'content')]
class Collection implements TenantScoped, RecordsEventsInterface
{
    use RecordsEvents;

    #[ORM\Column(type: 'boolean')]
    private bool $inArchive = false;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'collection_id')]
        private CollectionId $id,
        #[ORM\Column(type: 'organizer_id')]
        private OrganizerId $organizerId,
        #[ORM\Column(type: 'event_id')]
        private EventId $eventId,
        #[ORM\Column(type: 'string', length: 200)]
        private string $title,
        #[ORM\Column(type: 'string', length: 20, enumType: CollectionVisibility::class)]
        private CollectionVisibility $visibility,
        #[ORM\Column(type: 'string', length: 2000, nullable: true)]
        private ?string $externalUrl,
        #[ORM\Column(type: 'string', length: 64, nullable: true)]
        private ?string $linkToken,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $createdAt,
    ) {}

    /**
     * @param string|null $externalUrl null = an internal gallery of uploaded photos
     */
    public static function create(CollectionId $id, OrganizerId $organizer, EventId $event, string $title, CollectionVisibility $visibility, ?string $externalUrl, \DateTimeImmutable $now): self
    {
        $token = CollectionVisibility::LinkOnly === $visibility ? bin2hex(random_bytes(24)) : null;
        $collection = new self($id, $organizer, $event, trim($title), $visibility, $externalUrl, $token, $now);
        $collection->record(new CollectionChanged($organizer, $id, $event, 'created', $collection->snapshot()));

        return $collection;
    }

    public function publish(\DateTimeImmutable $now): void
    {
        if (null !== $this->publishedAt) {
            return;
        }
        $this->publishedAt = $now;
        $this->record(new CollectionChanged($this->organizerId, $this->id, $this->eventId, 'published', $this->snapshot()));
    }

    public function unpublish(): void
    {
        if (null === $this->publishedAt) {
            return;
        }
        $this->publishedAt = null;
        $this->record(new CollectionChanged($this->organizerId, $this->id, $this->eventId, 'unpublished', $this->snapshot()));
    }

    public function designateForArchive(bool $inArchive): void
    {
        if ($inArchive === $this->inArchive) {
            return;
        }
        $this->inArchive = $inArchive;
        $this->record(new CollectionChanged($this->organizerId, $this->id, $this->eventId, $inArchive ? 'archived' : 'removed_from_archive', $this->snapshot()));
    }

    /**
     * @throws CollectionIsExternal photos are uploaded only into an internal gallery
     */
    public function assertAcceptsPhotos(): void
    {
        if (null !== $this->externalUrl) {
            throw CollectionIsExternal::forId($this->id);
        }
    }

    public function id(): CollectionId
    {
        return $this->id;
    }

    public function organizerId(): OrganizerId
    {
        return $this->organizerId;
    }

    public function eventId(): EventId
    {
        return $this->eventId;
    }

    public function visibility(): CollectionVisibility
    {
        return $this->visibility;
    }

    public function linkToken(): ?string
    {
        return $this->linkToken;
    }

    public function isPublished(): bool
    {
        return null !== $this->publishedAt;
    }

    /**
     * Audit snapshot: no personal data, the link token is a secret and stays out too.
     *
     * @return array<string, scalar|null>
     */
    private function snapshot(): array
    {
        return [
            'title' => $this->title,
            'visibility' => $this->visibility->value,
            'external' => null !== $this->externalUrl,
            'inArchive' => $this->inArchive,
            'published' => null !== $this->publishedAt,
        ];
    }
}
