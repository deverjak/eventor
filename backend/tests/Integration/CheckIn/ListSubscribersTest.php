<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\CheckIn;

use Eventor\Catalog\Contract\Event\ActivityRescheduled;
use Eventor\Catalog\Contract\Event\EventCancelled;
use Eventor\Catalog\Contract\Event\EventPublished;
use Eventor\CheckIn\Application\CancelLists\CancelCheckInLists;
use Eventor\CheckIn\Application\ProvisionLists\ProvisionCheckInLists;
use Eventor\CheckIn\Application\RescheduleList\RescheduleCheckInList;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Tests\Support\Catalog\Json;
use Eventor\Tests\Support\CheckIn\CheckInHttpTestCase;

/**
 * CHK-01: the check-in lists follow the event. `catalog.event_published` creates one per activity, `activity_rescheduled`
 * moves its opening, `event_cancelled` closes them. Delivery is at-least-once, so every subscriber is idempotent.
 */
final class ListSubscribersTest extends CheckInHttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->openDesk();
    }

    private function organizer(): OrganizerId
    {
        return OrganizerId::fromString($this->owner['organizerId']);
    }

    public function test_publishing_the_event_created_a_list_per_activity_named_after_it(): void
    {
        $rows = $this->dbRows('SELECT activity_id, name, opens_at, cancelled_at FROM checkin.check_in_list WHERE event_id = ? ORDER BY name', [$this->event['eventId']]);

        self::assertCount(2, $rows);
        self::assertSame('Dětský závod', $rows[0]['name']);
        self::assertSame($this->event['kids'], $rows[0]['activity_id']);
        self::assertSame('Trail 5 km', $rows[1]['name']);
        self::assertSame('2026-11-21 09:00:00+00', $rows[1]['opens_at']);
        self::assertNull($rows[1]['cancelled_at']);
    }

    public function test_a_redelivery_of_the_event_creates_nothing_new(): void
    {
        $this->publishIntegrationEvent(new EventPublished($this->organizer(), EventId::fromString($this->event['eventId']), $this->event['slug'], new \DateTimeImmutable('2026-11-21 08:00:00+01:00'), new \DateTimeImmutable('2026-11-22 16:00:00+01:00'), [ActivityId::fromString($this->event['trail']), ActivityId::fromString((string) $this->event['kids'])], new \DateTimeImmutable('2026-11-01 12:00:00+01:00')));
        $this->drainCheckIn();

        self::assertSame(2, $this->dbInt('SELECT count(*) FROM checkin.check_in_list WHERE event_id = ?', [$this->event['eventId']]));
        self::assertSame($this->trailList, $this->listOf($this->event['trail']), 'the list keeps its id');
    }

    public function test_the_provisioning_command_adds_only_the_missing_lists(): void
    {
        $this->connection()->executeStatement('DELETE FROM checkin.check_in_list WHERE id = ?', [$this->kidsList]);

        $this->commandBus()->dispatch(new ProvisionCheckInLists($this->event['eventId'], [$this->event['trail'], (string) $this->event['kids']]), TenantStamp::of($this->organizer()));

        self::assertSame(2, $this->dbInt('SELECT count(*) FROM checkin.check_in_list WHERE event_id = ?', [$this->event['eventId']]));
        self::assertSame($this->trailList, $this->listOf($this->event['trail']));
        self::assertNotSame($this->kidsList, $this->listOf((string) $this->event['kids']));
    }

    public function test_an_activity_that_moved_moves_the_opening_of_its_list(): void
    {
        $this->publishIntegrationEvent(new ActivityRescheduled($this->organizer(), EventId::fromString($this->event['eventId']), ActivityId::fromString($this->event['trail']), new \DateTimeImmutable('2026-11-21 10:00:00+01:00'), new \DateTimeImmutable('2026-11-21 15:30:00+01:00'), true, new \DateTimeImmutable('2026-11-01 12:00:00+01:00')));
        $this->drainCheckIn();

        self::assertSame('2026-11-21 14:30:00+00', $this->dbString('SELECT opens_at FROM checkin.check_in_list WHERE id = ?', [$this->trailList]));
        self::assertSame('2026-11-22 09:00:00+00', $this->dbString('SELECT opens_at FROM checkin.check_in_list WHERE id = ?', [$this->kidsList]), 'the other list is untouched');
        $this->login(self::OWNER);
        $lists = $this->request('GET', $this->teamPath('/lists'));
        $opens = [];
        foreach (Json::arr($lists, 'items') as $item) {
            $opens[Json::str($item, 'id')] = Json::str($item, 'opensAt');
        }
        self::assertSame('2026-11-21T14:30:00+00:00', $opens[$this->trailList]);
    }

    public function test_rescheduling_an_activity_without_a_list_changes_nothing(): void
    {
        $this->commandBus()->dispatch(new RescheduleCheckInList(ActivityId::generate()->toString(), new \DateTimeImmutable('2026-12-01 10:00:00+01:00')), TenantStamp::of($this->organizer()));

        self::assertSame(2, $this->dbInt('SELECT count(*) FROM checkin.check_in_list WHERE event_id = ?', [$this->event['eventId']]));
    }

    public function test_a_cancelled_event_closes_its_lists_and_the_desk_refuses_them(): void
    {
        $paid = $this->paidParticipant();

        $this->publishIntegrationEvent(new EventCancelled($this->organizer(), EventId::fromString($this->event['eventId']), 'Storm', new \DateTimeImmutable('2026-11-01 12:00:00+01:00')));
        $this->drainCheckIn();
        $this->commandBus()->dispatch(new CancelCheckInLists($this->event['eventId']), TenantStamp::of($this->organizer()));

        self::assertSame(0, $this->dbInt('SELECT count(*) FROM checkin.check_in_list WHERE event_id = ? AND cancelled_at IS NULL', [$this->event['eventId']]));
        $this->login(self::OWNER);
        self::assertSame([], Json::arr($this->request('GET', $this->teamPath('/lists')), 'items'), 'closed lists are not offered');
        $this->post($this->teamPath('/lists/'.$this->trailList.'/check-ins'), ['participationId' => $paid['participationId']]);
        $this->assertProblem(409, 'check_in_list_closed');
    }
}
