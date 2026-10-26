<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\CheckIn\Domain;

use Eventor\CheckIn\Contract\CheckInSituation;
use Eventor\CheckIn\Domain\CheckInList;
use Eventor\CheckIn\Domain\Event\CheckInListCancelled;
use Eventor\CheckIn\Domain\Event\CheckInListProvisioned;
use Eventor\CheckIn\Domain\Event\CheckInListRescheduled;
use Eventor\CheckIn\Domain\Event\EscalationWasRaised;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use PHPUnit\Framework\TestCase;

final class CheckInListTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-11-01 12:00:00+01:00');
    }

    private function list(): CheckInList
    {
        return CheckInList::provision(OrganizerId::generate(), EventId::generate(), ActivityId::generate(), 'Trail 5 km', new \DateTimeImmutable('2026-11-21 10:00:00+01:00'), $this->now);
    }

    public function test_a_list_is_provisioned_open_with_an_event(): void
    {
        $list = $this->list();

        self::assertTrue($list->isOpen());
        self::assertSame('Trail 5 km', $list->name());
        self::assertEquals(new \DateTimeImmutable('2026-11-21 10:00:00+01:00'), $list->opensAt());
        $events = $list->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CheckInListProvisioned::class, $events[0]);
    }

    public function test_rescheduling_moves_the_opening_and_a_repeat_is_silent(): void
    {
        $list = $this->list();
        $list->releaseEvents();
        $new = new \DateTimeImmutable('2026-11-21 14:00:00+01:00');

        $list->reschedule($new, $this->now);
        $list->reschedule($new, $this->now);

        self::assertEquals($new, $list->opensAt());
        $events = $list->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CheckInListRescheduled::class, $events[0]);
        self::assertEquals(new \DateTimeImmutable('2026-11-21 10:00:00+01:00'), $events[0]->previousOpensAt);
    }

    public function test_cancelling_closes_the_list_once(): void
    {
        $list = $this->list();
        $list->releaseEvents();

        $list->cancel($this->now);
        $list->cancel($this->now);

        self::assertFalse($list->isOpen());
        $events = $list->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CheckInListCancelled::class, $events[0]);
    }

    public function test_an_escalation_carries_the_situation(): void
    {
        $list = $this->list();
        $list->releaseEvents();
        $participation = ParticipationId::generate();

        $list->escalate($participation, CheckInSituation::MissingConfirmation, $this->now);

        $events = $list->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(EscalationWasRaised::class, $events[0]);
        self::assertSame(CheckInSituation::MissingConfirmation, $events[0]->situation);
        self::assertTrue($events[0]->participationId->equals($participation));
        self::assertTrue($events[0]->listId->equals($list->id()));
    }
}
