<?php

declare(strict_types=1);

namespace Eventor\Content\Domain\Material;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Content\Contract\MaterialType;
use Eventor\Content\Domain\Event\MaterialChanged;
use Eventor\Shared\Domain\Event\RecordsEvents;
use Eventor\Shared\Domain\Event\RecordsEventsInterface;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\MaterialId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Tenant\TenantScoped;

/**
 * A post-event material (POST-04): results, a video, a document or the partners' thanks, always a link. A draft until
 * published; `inArchive` keeps it in the public archive of the finished event (POST-07).
 */
#[ORM\Entity]
#[ORM\Table(name: 'material', schema: 'content')]
class Material implements TenantScoped, RecordsEventsInterface
{
    use RecordsEvents;

    #[ORM\Column(type: 'boolean')]
    private bool $inArchive = false;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'material_id')]
        private MaterialId $id,
        #[ORM\Column(type: 'organizer_id')]
        private OrganizerId $organizerId,
        #[ORM\Column(type: 'event_id')]
        private EventId $eventId,
        #[ORM\Column(type: 'string', length: 200)]
        private string $title,
        #[ORM\Column(type: 'string', length: 2000)]
        private string $url,
        #[ORM\Column(type: 'string', length: 20, enumType: MaterialType::class)]
        private MaterialType $type,
        #[ORM\Column(type: 'integer')]
        private int $position,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $createdAt,
    ) {}

    public static function add(MaterialId $id, OrganizerId $organizer, EventId $event, string $title, string $url, MaterialType $type, int $position, \DateTimeImmutable $now): self
    {
        $material = new self($id, $organizer, $event, trim($title), $url, $type, $position, $now);
        $material->record(new MaterialChanged($organizer, $id, 'added', $material->snapshot()));

        return $material;
    }

    public function publish(\DateTimeImmutable $now): void
    {
        if (null !== $this->publishedAt) {
            return;
        }
        $this->publishedAt = $now;
        $this->record(new MaterialChanged($this->organizerId, $this->id, 'published', $this->snapshot()));
    }

    public function unpublish(): void
    {
        if (null === $this->publishedAt) {
            return;
        }
        $this->publishedAt = null;
        $this->record(new MaterialChanged($this->organizerId, $this->id, 'unpublished', $this->snapshot()));
    }

    public function designateForArchive(bool $inArchive): void
    {
        if ($inArchive === $this->inArchive) {
            return;
        }
        $this->inArchive = $inArchive;
        $this->record(new MaterialChanged($this->organizerId, $this->id, $inArchive ? 'archived' : 'removed_from_archive', $this->snapshot()));
    }

    public function id(): MaterialId
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

    /**
     * @return array<string, scalar|null>
     */
    private function snapshot(): array
    {
        return ['title' => $this->title, 'url' => $this->url, 'type' => $this->type->value, 'inArchive' => $this->inArchive, 'published' => null !== $this->publishedAt];
    }
}
