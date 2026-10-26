<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\CheckIn\Domain;

use Eventor\CheckIn\Contract\Exception\CheckInAlreadyVoided;
use Eventor\CheckIn\Domain\CheckInRecord;
use Eventor\CheckIn\Domain\Event\CheckedIn;
use Eventor\CheckIn\Domain\Event\CheckInWasVoided;
use Eventor\Shared\Domain\Id\CheckInListId;
use Eventor\Shared\Domain\Id\CheckInStationId;
use Eventor\Shared\Domain\Id\LedgerEntryId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use PHPUnit\Framework\TestCase;

final class CheckInRecordTest extends TestCase
{
    private OrganizerId $organizer;
    private ParticipationId $participation;
    private CheckInListId $list;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->organizer = OrganizerId::generate();
        $this->participation = ParticipationId::generate();
        $this->list = CheckInListId::generate();
        $this->now = new \DateTimeImmutable('2026-11-21 09:30:00+01:00');
    }

    private function record(?int $cash = null, ?LedgerEntryId $ledger = null): CheckInRecord
    {
        return CheckInRecord::record($this->organizer, $this->participation, $this->list, null, 'account-1', null, $this->now, $cash, $ledger);
    }

    public function test_an_arrival_is_valid_and_records_the_domain_event(): void
    {
        $record = $this->record();

        self::assertTrue($record->isValid());
        self::assertSame('account-1', $record->checkedInBy());
        self::assertNull($record->deviceLabel());
        self::assertEquals($this->now, $record->checkedInAt());
        self::assertTrue($record->organizerId()->equals($this->organizer));
        $events = $record->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CheckedIn::class, $events[0]);
        self::assertTrue($events[0]->participationId->equals($this->participation));
        self::assertSame([], $record->releaseEvents(), 'events are released once');
    }

    public function test_cash_taken_at_the_desk_is_remembered(): void
    {
        $ledger = LedgerEntryId::generate();

        self::assertSame(30000, $this->record(30000, $ledger)->cashMinor());
        self::assertNull($this->record()->cashMinor());
    }

    public function test_a_void_is_audited_with_the_reason(): void
    {
        $record = $this->record();
        $record->releaseEvents();

        $record->void('manager-1', 'Scanned the wrong ticket', $this->now->modify('+5 minutes'));

        self::assertFalse($record->isValid());
        $events = $record->releaseEvents();
        self::assertCount(1, $events);
        $voided = $events[0];
        self::assertInstanceOf(CheckInWasVoided::class, $voided);
        self::assertNull($voided->replacedBy);
        $change = $voided->auditChange();
        self::assertSame('check_in.voided', $change->action);
        self::assertSame('Scanned the wrong ticket', $change->reason);
        self::assertSame($record->id()->toString(), $change->entityId);
    }

    public function test_a_row_is_voided_only_once(): void
    {
        $record = $this->record();
        $record->void('manager-1', 'Mistake', $this->now);

        $this->expectException(CheckInAlreadyVoided::class);
        $record->void('manager-1', 'Again', $this->now);
    }

    public function test_a_replacement_points_back_and_keeps_what_is_not_corrected(): void
    {
        $record = $this->record(5000, LedgerEntryId::generate());
        $station = CheckInStationId::generate();
        $replacement = $record->replacement($station, null, 'manager-1', null);

        self::assertNotTrue($replacement->id()->equals($record->id()));
        self::assertTrue($replacement->participationId()->equals($this->participation));
        self::assertTrue($replacement->listId()->equals($this->list));
        self::assertEquals($station, $replacement->stationId());
        self::assertEquals($this->now, $replacement->checkedInAt(), 'the time is kept when it is not corrected');
        self::assertSame(5000, $replacement->cashMinor(), 'the cash stays with the arrival');
        self::assertSame([], $replacement->releaseEvents(), 'a correction is the same arrival: no second CheckedIn');
    }

    public function test_a_correction_voids_with_replaced_by_and_is_audited_as_a_correction(): void
    {
        $record = $this->record();
        $record->releaseEvents();
        $replacement = $record->replacement(null, $this->now->modify('-10 minutes'), 'manager-1', null);

        $record->void('manager-1', 'Arrived earlier', $this->now, $replacement->id());

        $events = $record->releaseEvents();
        self::assertInstanceOf(CheckInWasVoided::class, $events[0]);
        self::assertTrue($events[0]->replacedBy?->equals($replacement->id()) ?? false);
        self::assertSame('check_in.corrected', $events[0]->auditChange()->action);
        self::assertEquals($this->now->modify('-10 minutes'), $replacement->checkedInAt());
    }
}
