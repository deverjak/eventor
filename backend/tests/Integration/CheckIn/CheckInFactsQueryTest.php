<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\CheckIn;

use Eventor\CheckIn\Contract\CheckInQuery;
use Eventor\CheckIn\Contract\Exception\CheckInListNotFound;
use Eventor\Shared\Domain\Id\CheckInListId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Tests\Support\CheckIn\CheckInHttpTestCase;

/**
 * The `CheckInQuery` facade of the Contract, as Communication and Reporting use it: lists, the valid check-in of a
 * participation, the "arrived" narrowing and the counters.
 */
final class CheckInFactsQueryTest extends CheckInHttpTestCase
{
    private CheckInQuery $facade;

    private OrganizerId $organizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->openDesk();
        $this->facade = $this->service(CheckInQuery::class);
        $this->organizer = OrganizerId::fromString($this->owner['organizerId']);
        $this->login(self::OWNER);
    }

    private function arrive(string $participation): void
    {
        $this->post($this->teamPath('/lists/'.$this->trailList.'/check-ins'), ['participationId' => $participation, 'acknowledgeWarning' => true]);
        self::assertResponseStatusCodeSame(201);
    }

    public function test_it_lists_the_lists_of_an_event(): void
    {
        $lists = $this->facade->lists($this->organizer, EventId::fromString($this->event['eventId']));

        self::assertCount(2, $lists);
        $byActivity = [];
        foreach ($lists as $list) {
            $byActivity[$list->activityId->toString()] = $list;
        }
        self::assertSame('Trail 5 km', $byActivity[$this->event['trail']]->name);
        self::assertSame($this->trailList, $byActivity[$this->event['trail']]->id->toString());
        self::assertEquals(new \DateTimeImmutable('2026-11-21T10:00:00+01:00'), $byActivity[$this->event['trail']]->opensAt);
    }

    public function test_it_does_not_list_the_lists_of_another_organizer(): void
    {
        self::assertSame([], $this->facade->lists(OrganizerId::generate(), EventId::fromString($this->event['eventId'])));
    }

    public function test_it_knows_the_valid_check_in_of_a_participation(): void
    {
        $jana = $this->participant();
        $this->arrive($jana['participationId']);

        $record = $this->facade->recordFor($this->organizer, ParticipationId::fromString($jana['participationId']));

        self::assertNotNull($record);
        self::assertSame($this->trailList, $record->listId->toString());
        self::assertSame($this->owner['userAccountId'], $record->checkedInBy);
        self::assertNull($this->facade->recordFor(OrganizerId::generate(), ParticipationId::fromString($jana['participationId'])));
        self::assertNull($this->facade->recordFor($this->organizer, ParticipationId::generate()));
    }

    public function test_a_voided_check_in_is_no_longer_the_record_of_the_participation(): void
    {
        $jana = $this->participant();
        $this->arrive($jana['participationId']);
        $checkIn = $this->dbString('SELECT id FROM checkin.check_in WHERE participation_id = ?', [$jana['participationId']]);
        $this->request('POST', $this->teamPath('/check-ins/'.$checkIn.'/void'), ['reason' => 'Mistake']);

        self::assertNull($this->facade->recordFor($this->organizer, ParticipationId::fromString($jana['participationId'])));
    }

    public function test_it_narrows_participations_to_those_who_arrived(): void
    {
        $arrived = $this->participant('Alena');
        $absent = $this->participant('Bedřich');
        $this->arrive($arrived['participationId']);

        $narrowed = $this->facade->checkedIn($this->organizer, [ParticipationId::fromString($arrived['participationId']), ParticipationId::fromString($absent['participationId'])]);

        self::assertCount(1, $narrowed);
        self::assertSame($arrived['participationId'], $narrowed[0]->toString());
        self::assertSame([], $this->facade->checkedIn($this->organizer, []));
        self::assertSame([], $this->facade->checkedIn(OrganizerId::generate(), [ParticipationId::fromString($arrived['participationId'])]));
    }

    public function test_it_counts_expected_and_arrived_per_list(): void
    {
        $alena = $this->participant('Alena');
        $this->participant('Bedřich');
        $this->arrive($alena['participationId']);

        $counts = $this->facade->counts($this->organizer, CheckInListId::fromString($this->trailList));

        self::assertSame(2, $counts->expected);
        self::assertSame(1, $counts->checkedIn);
        self::assertSame(0, $this->facade->counts($this->organizer, CheckInListId::fromString($this->kidsList))->expected);
    }

    public function test_the_counters_of_an_unknown_or_foreign_list_are_not_found(): void
    {
        $this->expectException(CheckInListNotFound::class);
        $this->facade->counts(OrganizerId::generate(), CheckInListId::fromString($this->trailList));
    }
}
