<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Structure;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Catalog\Domain\Event\CapacityLimitChanged;
use Eventor\Catalog\Domain\Event\VariantChanged;
use Eventor\Catalog\Domain\Exception\VersionConflict;
use Eventor\Shared\Domain\Capacity\CapacityScope;
use Eventor\Shared\Domain\Capacity\CapacityUnit;
use Eventor\Shared\Domain\Event\RecordsEvents;
use Eventor\Shared\Domain\Event\RecordsEventsInterface;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\CapacityBucketId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\VariantId;
use Eventor\Shared\Domain\Money;
use Eventor\Shared\Domain\Tenant\TenantScoped;

/**
 * A price level of an activity (EVT-06): "Jednotlivec 300 Kč", "Tým 4 os. 1 200 Kč". Money is `price_minor` +
 * `price_currency`, never a float.
 */
#[ORM\Entity]
#[ORM\Table(name: 'variant', schema: 'catalog')]
class Variant implements TenantScoped, RecordsEventsInterface
{
    use HasCapacity;
    use RecordsEvents;

    #[ORM\Column(type: 'string', length: 200)]
    private string $name;

    #[ORM\Embedded(class: Money::class, columnPrefix: 'price_')]
    private Money $price;

    #[ORM\Column(type: 'string', length: 8, enumType: CapacityUnit::class)]
    private CapacityUnit $capacityUnit = CapacityUnit::Seat;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $teamSize;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $minAge;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $maxAge;

    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 1;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'variant_id')]
        private VariantId $id,
        #[ORM\Column(type: 'organizer_id')]
        private OrganizerId $organizerId,
        #[ORM\Column(type: 'event_id')]
        private EventId $eventId,
        #[ORM\Column(type: 'activity_id')]
        private ActivityId $activityId,
        VariantDetails $details,
        #[ORM\Column(type: 'integer')]
        private int $position,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $createdAt,
    ) {
        $this->capacityBucketId = CapacityBucketId::generate();
        $this->apply($details);
    }

    public static function create(VariantId $id, OrganizerId $organizerId, EventId $eventId, ActivityId $activityId, VariantDetails $details, int $position, \DateTimeImmutable $now, ?int $capacityLimit = null): self
    {
        $variant = new self($id, $organizerId, $eventId, $activityId, $details, $position, $now);
        $variant->capacityLimit = $capacityLimit;
        $variant->record(new VariantChanged($organizerId, $eventId, $activityId, $id, 'added'));

        return $variant;
    }

    public function id(): VariantId
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

    public function activityId(): ActivityId
    {
        return $this->activityId;
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

    public function price(): Money
    {
        return $this->price;
    }

    public function capacityUnit(): CapacityUnit
    {
        return $this->capacityUnit;
    }

    public function details(): VariantDetails
    {
        return new VariantDetails($this->name, $this->price, $this->teamSize, $this->minAge, $this->maxAge);
    }

    public function assertVersion(?int $expected): void
    {
        if (null !== $expected && $expected !== $this->version) {
            throw VersionConflict::for('The variant', $expected, $this->version);
        }
    }

    /**
     * @return list<string> the changed fields
     */
    public function change(VariantDetails $new): array
    {
        $changed = $this->details()->diff($new);
        $this->apply($new);
        $this->record(new VariantChanged($this->organizerId, $this->eventId, $this->activityId, $this->id, 'updated', $changed));

        return $changed;
    }

    public function changeCapacityLimit(?int $limit, bool $belowOccupancy, ?string $reason, bool $live): void
    {
        $previous = $this->capacityLimit;
        $this->capacityLimit = $limit;
        $this->record(new CapacityLimitChanged($this->organizerId, $this->eventId, CapacityScope::Variant, $this->id->toString(), $this->capacityBucketId, $previous, $limit, $belowOccupancy, $live, $reason));
    }

    private function apply(VariantDetails $details): void
    {
        $this->name = $details->name;
        $this->price = $details->price;
        $this->teamSize = $details->teamSize;
        $this->minAge = $details->minAge;
        $this->maxAge = $details->maxAge;
        $this->capacityUnit = null !== $details->teamSize && $details->teamSize > 1 ? CapacityUnit::Team : CapacityUnit::Seat;
    }
}
