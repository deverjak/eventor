<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Program;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Catalog\Contract\ProgramVisibility;
use Eventor\Catalog\Domain\Event\ProgramItemModified;
use Eventor\Shared\Domain\Event\RecordsEvents;
use Eventor\Shared\Domain\Event\RecordsEventsInterface;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ProgramItemId;
use Eventor\Shared\Domain\Tenant\TenantScoped;

/**
 * One item of the schedule (PRG-01): a time slot with an optional link to an activity (PRG-02). It stays editable after
 * the event is published; a change marked "important" offers a notification of the affected participants (PRG-03).
 */
#[ORM\Entity]
#[ORM\Table(name: 'program_item', schema: 'catalog')]
class ProgramItem implements TenantScoped, RecordsEventsInterface
{
    use RecordsEvents;

    #[ORM\Column(type: 'string', length: 200)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description;

    #[ORM\Column(type: 'string', length: 200, nullable: true)]
    private ?string $location;

    #[ORM\Column(type: 'string', length: 200, nullable: true)]
    private ?string $responsible;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $endsAt;

    #[ORM\Column(type: 'activity_id', nullable: true)]
    private ?ActivityId $activityId;

    #[ORM\Column(type: 'string', length: 16, enumType: ProgramVisibility::class)]
    private ProgramVisibility $visibility;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $updatedAt;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'program_item_id')]
        private ProgramItemId $id,
        #[ORM\Column(type: 'organizer_id')]
        private OrganizerId $organizerId,
        #[ORM\Column(type: 'event_id')]
        private EventId $eventId,
        ProgramItemDetails $details,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $createdAt,
    ) {
        $this->updatedAt = $createdAt;
        $this->apply($details);
    }

    public static function create(ProgramItemId $id, OrganizerId $organizerId, EventId $eventId, ProgramItemDetails $details, bool $important, bool $live, \DateTimeImmutable $now): self
    {
        $item = new self($id, $organizerId, $eventId, $details, $now);
        $item->record(new ProgramItemModified($organizerId, $eventId, $id, $details->activityId, 'added', $important, $live));

        return $item;
    }

    public function id(): ProgramItemId
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

    public function activityId(): ?ActivityId
    {
        return $this->activityId;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function change(ProgramItemDetails $details, bool $important, bool $live, \DateTimeImmutable $now): void
    {
        $this->apply($details);
        $this->updatedAt = $now;
        $this->record(new ProgramItemModified($this->organizerId, $this->eventId, $this->id, $this->activityId, 'updated', $important, $live));
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function apply(ProgramItemDetails $details): void
    {
        $this->title = $details->title;
        $this->description = $details->description;
        $this->location = $details->location;
        $this->responsible = $details->responsible;
        $this->startsAt = $details->startsAt;
        $this->endsAt = $details->endsAt;
        $this->activityId = $details->activityId;
        $this->visibility = $details->visibility;
    }
}
