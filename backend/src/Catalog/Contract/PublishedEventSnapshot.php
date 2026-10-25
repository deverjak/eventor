<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract;

use Eventor\Shared\Domain\Currency;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\AddOnId;
use Eventor\Shared\Domain\Id\CapacityBucketId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\FormVersionId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\VariantId;
use Eventor\Shared\Domain\Payment\PaymentMethod;

/**
 * The published registration offer of an event (chapter 04, path 2). Immutable for a given `formVersionId` and
 * `priceListVersion`. Registration reads everything it needs to validate, price and reserve from here.
 *
 * Ages are checked against `ageReferenceDate`. `paymentMethods` is what the organizer enabled for the event (a free
 * order always uses `PaymentMethod::Free`, which is not listed). `eventBucketId` is the event-level capacity bucket
 * (null = unlimited).
 */
final readonly class PublishedEventSnapshot
{
    /**
     * @param list<PaymentMethod> $paymentMethods
     * @param list<ActivitySnapshot> $activities
     * @param list<AddOnSnapshot> $addOns
     */
    public function __construct(
        public OrganizerId $organizerId,
        public EventId $eventId,
        public string $slug,
        public string $name,
        public string $timezone,
        public \DateTimeImmutable $startsAt,
        public \DateTimeImmutable $endsAt,
        public PublicationState $publicationState,
        public RegistrationState $registrationState,
        public ?\DateTimeImmutable $registrationOpensAt,
        public ?\DateTimeImmutable $registrationClosesAt,
        public FormVersionId $formVersionId,
        public int $priceListVersion,
        public Currency $currency,
        public array $paymentMethods,
        public int $holdTtlHours,
        public int $dueDays,
        public bool $autoRestoreOnLatePayment,
        public bool $selfServiceCancellationAllowed,
        public ?\DateTimeImmutable $selfServiceCancellationDeadline,
        public \DateTimeImmutable $ageReferenceDate,
        public ?CapacityBucketId $eventBucketId,
        public array $activities,
        public array $addOns,
    ) {}

    public function activity(ActivityId $id): ?ActivitySnapshot
    {
        foreach ($this->activities as $activity) {
            if ($activity->id->equals($id)) {
                return $activity;
            }
        }

        return null;
    }

    public function variant(VariantId $id): ?VariantSnapshot
    {
        foreach ($this->activities as $activity) {
            foreach ($activity->variants as $variant) {
                if ($variant->id->equals($id)) {
                    return $variant;
                }
            }
        }

        return null;
    }

    public function addOn(AddOnId $id): ?AddOnSnapshot
    {
        foreach ($this->addOns as $addOn) {
            if ($addOn->id->equals($id)) {
                return $addOn;
            }
        }

        return null;
    }

    /**
     * Event-level gate of the public endpoint; the activities are checked separately (`ActivitySnapshot::acceptsRegistrations()`).
     */
    public function acceptsRegistrations(\DateTimeImmutable $now): bool
    {
        return RegistrationState::Open === $this->registrationState
            && (null === $this->registrationOpensAt || $this->registrationOpensAt <= $now)
            && (null === $this->registrationClosesAt || $now < $this->registrationClosesAt);
    }
}
