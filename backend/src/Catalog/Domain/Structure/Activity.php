<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Structure;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Catalog\Contract\RegistrationState;
use Eventor\Catalog\Domain\Event\ActivityChanged;
use Eventor\Catalog\Domain\Event\ActivityTimeChanged;
use Eventor\Catalog\Domain\Event\CapacityLimitChanged;
use Eventor\Catalog\Domain\Event\RegistrationWindowChanged;
use Eventor\Catalog\Domain\Exception\VersionConflict;
use Eventor\Catalog\Domain\Workflow\Transitions;
use Eventor\Shared\Domain\Capacity\CapacityScope;
use Eventor\Shared\Domain\Event\RecordsEvents;
use Eventor\Shared\Domain\Event\RecordsEventsInterface;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\CapacityBucketId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Tenant\TenantScoped;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * A part of an event that people register for (EVT-05): a race, a workshop, a day. A "simple" event has one implicit
 * activity. It has its own registration window (`registration_window` state machine) and its own capacity bucket.
 */
#[ORM\Entity]
#[ORM\Table(name: 'activity', schema: 'catalog')]
class Activity implements TenantScoped, RecordsEventsInterface
{
    use HasCapacity;
    use RecordsEvents;

    #[ORM\Column(type: 'string', length: 200)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description;

    #[ORM\Column(type: 'string', length: 200, nullable: true)]
    private ?string $location;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $startsAt;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $endsAt;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $registrationOpensAt;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $registrationClosesAt;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $minAge;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $maxAge;

    #[ORM\Column(type: 'boolean')]
    private bool $minorsOnly;

    #[ORM\Column(type: 'string', length: 16, enumType: RegistrationState::class)]
    private RegistrationState $registrationState = RegistrationState::NotOpen;

    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 1;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'activity_id')]
        private ActivityId $id,
        #[ORM\Column(type: 'organizer_id')]
        private OrganizerId $organizerId,
        #[ORM\Column(type: 'event_id')]
        private EventId $eventId,
        ActivityDetails $details,
        #[ORM\Column(type: 'boolean')]
        private bool $implicit,
        #[ORM\Column(type: 'integer')]
        private int $position,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $createdAt,
    ) {
        $this->capacityBucketId = CapacityBucketId::generate();
        $this->name = $details->name;
        $this->description = $details->description;
        $this->location = $details->location;
        $this->startsAt = $details->startsAt;
        $this->endsAt = $details->endsAt;
        $this->registrationOpensAt = $details->registrationOpensAt;
        $this->registrationClosesAt = $details->registrationClosesAt;
        $this->minAge = $details->minAge;
        $this->maxAge = $details->maxAge;
        $this->minorsOnly = $details->minorsOnly;
    }

    public static function create(ActivityId $id, OrganizerId $organizerId, EventId $eventId, ActivityDetails $details, bool $implicit, int $position, \DateTimeImmutable $now, ?int $capacityLimit = null): self
    {
        $activity = new self($id, $organizerId, $eventId, $details, $implicit, $position, $now);
        $activity->capacityLimit = $capacityLimit;
        $activity->record(new ActivityChanged($organizerId, $eventId, $id, 'added'));

        return $activity;
    }

    public function id(): ActivityId
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

    public function name(): string
    {
        return $this->name;
    }

    public function isImplicit(): bool
    {
        return $this->implicit;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function startsAt(): ?\DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function minorsOnly(): bool
    {
        return $this->minorsOnly;
    }

    public function registrationState(): RegistrationState
    {
        return $this->registrationState;
    }

    public function details(): ActivityDetails
    {
        return new ActivityDetails(
            $this->name,
            $this->description,
            $this->location,
            $this->startsAt,
            $this->endsAt,
            $this->registrationOpensAt,
            $this->registrationClosesAt,
            $this->minAge,
            $this->maxAge,
            $this->minorsOnly,
        );
    }

    public function assertVersion(?int $expected): void
    {
        if (null !== $expected && $expected !== $this->version) {
            throw VersionConflict::for('The activity', $expected, $this->version);
        }
    }

    /**
     * @param bool $important the organizer wants the participants of this activity to be notified of a time change
     * @param bool $live      the event is published
     */
    public function change(ActivityDetails $new, bool $important, bool $live): void
    {
        $changed = $this->details()->diff($new);
        $previousStart = $this->startsAt;

        $this->name = $new->name;
        $this->description = $new->description;
        $this->location = $new->location;
        $this->startsAt = $new->startsAt;
        $this->endsAt = $new->endsAt;
        $this->registrationOpensAt = $new->registrationOpensAt;
        $this->registrationClosesAt = $new->registrationClosesAt;
        $this->minAge = $new->minAge;
        $this->maxAge = $new->maxAge;
        $this->minorsOnly = $new->minorsOnly;

        $this->record(new ActivityChanged($this->organizerId, $this->eventId, $this->id, 'updated', $changed));
        if (\in_array('starts_at', $changed, true)) {
            $this->record(new ActivityTimeChanged($this->organizerId, $this->eventId, $this->id, $previousStart, $this->startsAt, $important, $live));
        }
    }

    public function changeCapacityLimit(?int $limit, bool $belowOccupancy, ?string $reason, bool $live): void
    {
        $previous = $this->capacityLimit;
        $this->capacityLimit = $limit;
        $this->record(new CapacityLimitChanged($this->organizerId, $this->eventId, CapacityScope::Activity, $this->id->toString(), $this->capacityBucketId, $previous, $limit, $belowOccupancy, $live, $reason));
    }

    /**
     * @param array<string, mixed> $context
     */
    public function transitionRegistration(WorkflowInterface $machine, string $transition, ?string $reason, array $context): void
    {
        $from = $this->registrationState;
        Transitions::apply($machine, $this, $transition, $context);
        $this->record(new RegistrationWindowChanged($this->organizerId, $this->eventId, $this->id, $from, $this->registrationState, $reason));
    }

    /** Marking store of `registration_window`. @internal */
    public function getRegistrationState(): RegistrationState
    {
        return $this->registrationState;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @internal marking store of `registration_window`; use transitionRegistration()
     */
    public function setRegistrationState(RegistrationState $state, array $context = []): void
    {
        $this->registrationState = $state;
    }
}
