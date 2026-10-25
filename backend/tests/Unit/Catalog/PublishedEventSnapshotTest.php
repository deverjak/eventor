<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Catalog;

use Eventor\Catalog\Contract\ActivitySnapshot;
use Eventor\Catalog\Contract\AddOnSnapshot;
use Eventor\Catalog\Contract\PublicationState;
use Eventor\Catalog\Contract\PublishedEventSnapshot;
use Eventor\Catalog\Contract\RegistrationState;
use Eventor\Catalog\Contract\VariantSnapshot;
use Eventor\Shared\Domain\Capacity\CapacityUnit;
use Eventor\Shared\Domain\Currency;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\AddOnId;
use Eventor\Shared\Domain\Id\CapacityBucketId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\FormVersionId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\VariantId;
use Eventor\Shared\Domain\Money;
use Eventor\Shared\Domain\Payment\PaymentMethod;
use PHPUnit\Framework\TestCase;

final class PublishedEventSnapshotTest extends TestCase
{
    private ActivityId $activityId;
    private VariantId $variantId;
    private AddOnId $addOnId;

    public function test_the_snapshot_finds_activities_variants_and_add_ons_by_id(): void
    {
        $snapshot = $this->snapshot(RegistrationState::Open);

        self::assertSame('10 km', $snapshot->variant($this->variantId)?->name);
        self::assertSame($this->activityId->toString(), $snapshot->activity($this->activityId)?->id->toString());
        self::assertSame('Obed', $snapshot->addOn($this->addOnId)?->name);
        self::assertNull($snapshot->activity(ActivityId::generate()));
        self::assertNull($snapshot->variant(VariantId::generate()));
        self::assertNull($snapshot->addOn(AddOnId::generate()));
    }

    public function test_event_registration_is_accepted_only_when_open_and_inside_the_window(): void
    {
        $opens = new \DateTimeImmutable('2026-10-01T08:00:00+00:00');
        $closes = new \DateTimeImmutable('2026-10-20T20:00:00+00:00');
        $open = $this->snapshot(RegistrationState::Open, $opens, $closes);

        self::assertFalse($open->acceptsRegistrations($opens->modify('-1 second')), 'before the window');
        self::assertTrue($open->acceptsRegistrations($opens), 'opens inclusive');
        self::assertTrue($open->acceptsRegistrations($closes->modify('-1 second')));
        self::assertFalse($open->acceptsRegistrations($closes), 'closes exclusive');
        self::assertFalse($this->snapshot(RegistrationState::Paused)->acceptsRegistrations($opens), 'paused');
        self::assertTrue($this->snapshot(RegistrationState::Open)->acceptsRegistrations($opens), 'no window = open ended');
    }

    public function test_an_activity_accepts_registrations_only_when_open(): void
    {
        $activity = $this->snapshot(RegistrationState::Open)->activity($this->activityId);

        self::assertInstanceOf(ActivitySnapshot::class, $activity);
        self::assertTrue($activity->acceptsRegistrations());
        self::assertSame(PublicationState::Published, $this->snapshot(RegistrationState::Open)->publicationState);
    }

    private function snapshot(RegistrationState $state, ?\DateTimeImmutable $opens = null, ?\DateTimeImmutable $closes = null): PublishedEventSnapshot
    {
        $this->activityId = ActivityId::generate();
        $this->variantId = VariantId::generate();
        $this->addOnId = AddOnId::generate();

        return new PublishedEventSnapshot(
            organizerId: OrganizerId::generate(),
            eventId: EventId::generate(),
            slug: 'borsky-podzim',
            name: 'Borsky podzim',
            timezone: 'Europe/Prague',
            startsAt: new \DateTimeImmutable('2026-11-14T08:00:00+00:00'),
            endsAt: new \DateTimeImmutable('2026-11-15T16:00:00+00:00'),
            publicationState: PublicationState::Published,
            registrationState: $state,
            registrationOpensAt: $opens,
            registrationClosesAt: $closes,
            formVersionId: FormVersionId::generate(),
            priceListVersion: 3,
            currency: Currency::CZK,
            paymentMethods: [PaymentMethod::BankTransfer, PaymentMethod::Cash],
            holdTtlHours: 72,
            dueDays: 7,
            autoRestoreOnLatePayment: false,
            selfServiceCancellationAllowed: true,
            selfServiceCancellationDeadline: null,
            ageReferenceDate: new \DateTimeImmutable('2026-11-14'),
            eventBucketId: CapacityBucketId::generate(),
            activities: [new ActivitySnapshot(
                id: $this->activityId,
                name: 'Beh',
                startsAt: null,
                endsAt: null,
                registrationState: RegistrationState::Open,
                minAge: null,
                maxAge: null,
                minorsOnly: false,
                capacityBucketId: CapacityBucketId::generate(),
                variants: [new VariantSnapshot($this->variantId, $this->activityId, '10 km', Money::of(25_000), CapacityBucketId::generate(), CapacityUnit::Seat, null, 15, null)],
                waves: [],
            )],
            addOns: [new AddOnSnapshot($this->addOnId, 'Obed', Money::of(9_000), true, 2)],
        );
    }
}
