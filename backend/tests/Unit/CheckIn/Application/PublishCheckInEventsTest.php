<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\CheckIn\Application;

use Eventor\CheckIn\Application\EventPublisher\PublishCheckInEvents;
use Eventor\CheckIn\Contract\CheckInSituation;
use Eventor\CheckIn\Contract\Event\CheckInVoided;
use Eventor\CheckIn\Contract\Event\EscalationRaised;
use Eventor\CheckIn\Contract\Event\ParticipationCheckedIn;
use Eventor\CheckIn\Domain\Event\CheckedIn;
use Eventor\CheckIn\Domain\Event\CheckInWasVoided;
use Eventor\CheckIn\Domain\Event\EscalationWasRaised;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Eventor\Shared\Domain\Id\CheckInId;
use Eventor\Shared\Domain\Id\CheckInListId;
use Eventor\Shared\Domain\Id\CheckInStationId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The domain events of CheckIn become the public integration events, on the tenant of the organizer, with ids only.
 */
final class PublishCheckInEventsTest extends TestCase
{
    private OrganizerId $organizer;

    private ParticipationId $participation;

    private CheckInListId $list;

    private \DateTimeImmutable $at;


    private MessageBusInterface&\Countable $bus;

    private PublishCheckInEvents $publisher;

    protected function setUp(): void
    {
        $this->organizer = OrganizerId::generate();
        $this->participation = ParticipationId::generate();
        $this->list = CheckInListId::generate();
        $this->at = new \DateTimeImmutable('2026-11-21 09:30:00+01:00');
        $this->bus = new class implements MessageBusInterface, \Countable {
            /** @var list<Envelope> */
            public array $sink = [];

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                return $this->sink[] = Envelope::wrap($message, $stamps);
            }

            public function count(): int
            {
                return \count($this->sink);
            }
        };
        $this->publisher = new PublishCheckInEvents($this->bus);
    }

    private function only(): Envelope
    {
        self::assertCount(1, $this->bus);
        \assert(property_exists($this->bus, 'sink') && \is_array($this->bus->sink) && $this->bus->sink[0] instanceof Envelope);

        return $this->bus->sink[0];
    }

    public function test_an_arrival_is_published_as_participation_checked_in(): void
    {
        $checkIn = CheckInId::generate();
        $station = CheckInStationId::generate();

        $this->publisher->checkedIn(new CheckedIn($this->organizer, $checkIn, $this->participation, $this->list, $station, $this->at));

        $envelope = $this->only();
        $event = $envelope->getMessage();
        self::assertInstanceOf(ParticipationCheckedIn::class, $event);
        self::assertTrue($event->checkInId->equals($checkIn));
        self::assertTrue($event->participationId->equals($this->participation));
        self::assertTrue($event->listId->equals($this->list));
        self::assertEquals($station, $event->stationId);
        self::assertEquals($this->at, $event->checkedInAt);
        self::assertEquals($this->at, $event->occurredAt);
        self::assertSame($this->organizer->toString(), $envelope->last(TenantStamp::class)?->organizerId);
    }

    public function test_a_void_is_published_with_the_replacement(): void
    {
        $checkIn = CheckInId::generate();
        $replacement = CheckInId::generate();

        $this->publisher->voided(new CheckInWasVoided($this->organizer, $checkIn, $this->participation, $this->list, 'manager', 'Mistake', $replacement, $this->at));

        $event = $this->only()->getMessage();
        self::assertInstanceOf(CheckInVoided::class, $event);
        self::assertTrue($event->checkInId->equals($checkIn));
        self::assertTrue($event->replacedBy?->equals($replacement) ?? false);
        self::assertEquals($this->at, $event->occurredAt);
    }

    public function test_an_escalation_is_published_with_its_situation_and_no_name(): void
    {
        $this->publisher->escalated(new EscalationWasRaised($this->organizer, $this->list, $this->participation, CheckInSituation::MissingConfirmation, $this->at));

        $event = $this->only()->getMessage();
        self::assertInstanceOf(EscalationRaised::class, $event);
        self::assertSame(CheckInSituation::MissingConfirmation, $event->situation);
        self::assertTrue($event->participationId->equals($this->participation));
        self::assertTrue($event->listId->equals($this->list));
        self::assertSame($this->organizer->toString(), $this->only()->last(TenantStamp::class)?->organizerId);
    }
}
