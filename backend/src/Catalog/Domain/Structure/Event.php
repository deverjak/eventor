<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Structure;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Catalog\Contract\PublicationState;
use Eventor\Catalog\Contract\RegistrationState;
use Eventor\Catalog\Domain\Event\ActivityChanged;
use Eventor\Catalog\Domain\Event\CapacityLimitChanged;
use Eventor\Catalog\Domain\Event\EventCreated;
use Eventor\Catalog\Domain\Event\EventDetailsUpdated;
use Eventor\Catalog\Domain\Event\PaymentRulesChanged;
use Eventor\Catalog\Domain\Event\PriceListChanged;
use Eventor\Catalog\Domain\Event\ProgramItemModified;
use Eventor\Catalog\Domain\Event\PublicationChanged;
use Eventor\Catalog\Domain\Event\RegistrationWindowChanged;
use Eventor\Catalog\Domain\Event\VariantChanged;
use Eventor\Catalog\Domain\Exception\VersionConflict;
use Eventor\Catalog\Domain\Workflow\Transitions;
use Eventor\Shared\Domain\Capacity\CapacityScope;
use Eventor\Shared\Domain\Event\RecordsEvents;
use Eventor\Shared\Domain\Event\RecordsEventsInterface;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\CapacityBucketId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ProgramItemId;
use Eventor\Shared\Domain\Id\VariantId;
use Eventor\Shared\Domain\Tenant\TenantScoped;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * An event of one organizer (chapter 03, `catalog.event`). Two independent state dimensions (chapter 05): the
 * publication (`event_publication` state machine, EVT-03) and the registration window (`registration_window`,
 * EVT-04). The state setters are the marking store of the workflows; call the `transition*()` methods instead.
 */
#[ORM\Entity]
#[ORM\Table(name: 'event', schema: 'catalog')]
#[ORM\UniqueConstraint(name: 'event_organizer_slug_key', columns: ['organizer_id', 'slug'])]
class Event implements TenantScoped, RecordsEventsInterface
{
    use HasCapacity;
    use RecordsEvents;

    #[ORM\Column(type: 'string', length: 100)]
    private string $slug;

    #[ORM\Column(type: 'string', length: 200)]
    private string $name;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $shortDescription;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $coverImageUrl;

    #[ORM\Column(type: 'string', length: 200, nullable: true)]
    private ?string $contactName;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $contactEmail;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $contactPhone;

    #[ORM\Column(type: 'string', length: 64)]
    private string $timezone;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $endsAt;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $ageReferenceDate;

    #[ORM\Column(type: 'string', length: 16, enumType: EventVisibility::class)]
    private EventVisibility $visibility;

    #[ORM\Column(type: 'string', length: 16, enumType: PublicationState::class)]
    private PublicationState $publicationState = PublicationState::Draft;

    #[ORM\Column(type: 'string', length: 16, enumType: RegistrationState::class)]
    private RegistrationState $registrationState = RegistrationState::NotOpen;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $registrationOpensAt;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $registrationClosesAt;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json', options: ['jsonb' => true])]
    private array $paymentRules;

    /** @var list<array{name: string, address: string|null, mapUrl: string|null}> */
    #[ORM\Column(type: 'json', options: ['jsonb' => true])]
    private array $venues;

    #[ORM\Column(type: 'integer')]
    private int $priceListVersion = 1;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $cancellationReason = null;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 1;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'event_id')]
        private EventId $id,
        #[ORM\Column(type: 'organizer_id')]
        private OrganizerId $organizerId,
        string $slug,
        EventDetails $details,
        PaymentRules $paymentRules,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $createdAt,
    ) {
        $this->slug = $slug;
        $this->capacityBucketId = CapacityBucketId::generate();
        $this->paymentRules = $paymentRules->toArray();
        $this->updatedAt = $createdAt;
        $this->name = $details->name;
        $this->shortDescription = $details->shortDescription;
        $this->description = $details->description;
        $this->coverImageUrl = $details->coverImageUrl;
        $this->contactName = $details->contactName;
        $this->contactEmail = $details->contactEmail;
        $this->contactPhone = $details->contactPhone;
        $this->timezone = $details->timezone;
        $this->startsAt = $details->startsAt;
        $this->endsAt = $details->endsAt;
        $this->ageReferenceDate = $details->ageReferenceDate;
        $this->visibility = $details->visibility;
        $this->registrationOpensAt = $details->registrationOpensAt;
        $this->registrationClosesAt = $details->registrationClosesAt;
        $this->venues = array_map(static fn(Venue $venue): array => $venue->toArray(), $details->venues);
    }

    public static function create(EventId $id, OrganizerId $organizerId, string $slug, EventDetails $details, PaymentRules $paymentRules, \DateTimeImmutable $now): self
    {
        $event = new self($id, $organizerId, $slug, $details, $paymentRules, $now);
        $event->record(new EventCreated($organizerId, $id, $slug, $details->name));

        return $event;
    }

    public function id(): EventId
    {
        return $this->id;
    }

    public function organizerId(): OrganizerId
    {
        return $this->organizerId;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function priceListVersion(): int
    {
        return $this->priceListVersion;
    }

    public function publicationState(): PublicationState
    {
        return $this->publicationState;
    }

    public function registrationState(): RegistrationState
    {
        return $this->registrationState;
    }

    /** The event left the draft: other modules know it and its capacity buckets exist. */
    public function isLive(): bool
    {
        return PublicationState::Draft !== $this->publicationState;
    }

    public function isDraft(): bool
    {
        return PublicationState::Draft === $this->publicationState;
    }

    public function startsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function endsAt(): \DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function details(): EventDetails
    {
        return new EventDetails(
            $this->name,
            $this->shortDescription,
            $this->description,
            $this->coverImageUrl,
            $this->contactName,
            $this->contactEmail,
            $this->contactPhone,
            $this->timezone,
            $this->startsAt,
            $this->endsAt,
            $this->ageReferenceDate,
            $this->visibility,
            array_map(Venue::fromArray(...), $this->venues),
            $this->registrationOpensAt,
            $this->registrationClosesAt,
        );
    }

    public function paymentRules(): PaymentRules
    {
        return PaymentRules::fromArray($this->paymentRules);
    }

    public function assertVersion(?int $expected): void
    {
        if (null !== $expected && $expected !== $this->version) {
            throw VersionConflict::for('The event', $expected, $this->version);
        }
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function cancellationReason(): ?string
    {
        return $this->cancellationReason;
    }

    public function changeDetails(EventDetails $new, \DateTimeImmutable $now): void
    {
        $changed = $this->details()->diff($new);
        if ([] === $changed) {
            // Still a recorded change: the command was accepted, the audit shows that nothing differed.
            $this->record(new EventDetailsUpdated($this->organizerId, $this->id, [], false));

            return;
        }

        $this->name = $new->name;
        $this->shortDescription = $new->shortDescription;
        $this->description = $new->description;
        $this->coverImageUrl = $new->coverImageUrl;
        $this->contactName = $new->contactName;
        $this->contactEmail = $new->contactEmail;
        $this->contactPhone = $new->contactPhone;
        $this->timezone = $new->timezone;
        $this->startsAt = $new->startsAt;
        $this->endsAt = $new->endsAt;
        $this->ageReferenceDate = $new->ageReferenceDate;
        $this->visibility = $new->visibility;
        $this->registrationOpensAt = $new->registrationOpensAt;
        $this->registrationClosesAt = $new->registrationClosesAt;
        $this->venues = array_map(static fn(Venue $venue): array => $venue->toArray(), $new->venues);
        $this->updatedAt = $now;
        $this->record(new EventDetailsUpdated($this->organizerId, $this->id, $changed, $this->isLive()));
    }

    public function changePaymentRules(PaymentRules $new, \DateTimeImmutable $now): void
    {
        $before = $this->paymentRules;
        $this->paymentRules = $new->toArray();
        $this->updatedAt = $now;
        $this->record(new PaymentRulesChanged($this->organizerId, $this->id, $before, $this->paymentRules, $this->isLive()));
    }

    /**
     * A removed entity leaves the unit of work before its events could be collected, so removals are recorded by the
     * aggregate root, which is always loaded.
     */
    public function noteActivityRemoved(ActivityId $activity): void
    {
        $this->record(new ActivityChanged($this->organizerId, $this->id, $activity, 'removed'));
    }

    public function noteVariantRemoved(ActivityId $activity, VariantId $variant): void
    {
        $this->record(new VariantChanged($this->organizerId, $this->id, $activity, $variant, 'removed'));
    }

    public function noteProgramItemRemoved(ProgramItemId $item, ?ActivityId $activity, bool $important): void
    {
        $this->record(new ProgramItemModified($this->organizerId, $this->id, $item, $activity, 'removed', $important, $this->isLive()));
    }

    /** A price or the set of priced variants changed: Registration prices against a new list version. */
    public function bumpPriceListVersion(\DateTimeImmutable $now): void
    {
        ++$this->priceListVersion;
        $this->updatedAt = $now;
        $this->record(new PriceListChanged($this->organizerId, $this->id, $this->priceListVersion, $this->isLive()));
    }

    public function changeCapacityLimit(?int $limit, bool $belowOccupancy, ?string $reason, \DateTimeImmutable $now): void
    {
        $previous = $this->capacityLimit;
        $this->capacityLimit = $limit;
        $this->updatedAt = $now;
        $this->record(new CapacityLimitChanged($this->organizerId, $this->id, CapacityScope::Event, $this->id->toString(), $this->capacityBucketId, $previous, $limit, $belowOccupancy, $this->isLive(), $reason));
    }

    /** Draft only: the limit is part of the offer that is still being designed. */
    public function configureCapacityLimit(?int $limit, \DateTimeImmutable $now): void
    {
        $this->changeCapacityLimit($limit, false, null, $now);
    }

    /**
     * Applies a transition of the `event_publication` state machine and records what happened.
     *
     * @param list<ActivityId>     $activityIds
     * @param array<string, mixed> $context     `actor` (AuditActor) for the guards
     */
    public function transitionPublication(WorkflowInterface $machine, string $transition, array $activityIds, ?string $reason, array $context): void
    {
        $from = $this->publicationState;
        Transitions::apply($machine, $this, $transition, $context);
        if (PublicationState::Cancelled === $this->publicationState) {
            $this->cancellationReason = $reason;
        }
        $this->record(new PublicationChanged($this->organizerId, $this->id, $from, $this->publicationState, $this->slug, $this->startsAt, $this->endsAt, $activityIds, $reason));
    }

    /**
     * Applies a transition of the `registration_window` state machine of the event.
     *
     * @param array<string, mixed> $context
     */
    public function transitionRegistration(WorkflowInterface $machine, string $transition, ?string $reason, array $context): void
    {
        $from = $this->registrationState;
        Transitions::apply($machine, $this, $transition, $context);
        $this->record(new RegistrationWindowChanged($this->organizerId, $this->id, null, $from, $this->registrationState, $reason));
    }

    /** Marking store of `event_publication`. @internal */
    public function getPublicationState(): PublicationState
    {
        return $this->publicationState;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @internal marking store of `event_publication`; use transitionPublication()
     */
    public function setPublicationState(PublicationState $state, array $context = []): void
    {
        $this->publicationState = $state;
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
