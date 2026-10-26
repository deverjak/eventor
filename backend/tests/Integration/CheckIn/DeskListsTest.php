<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\CheckIn;

use Eventor\Identity\Domain\Membership\Role;
use Eventor\Tests\Support\Catalog\Json;
use Eventor\Tests\Support\CheckIn\CheckInHttpTestCase;

/**
 * CHK-01 and CHK-10 over HTTP: the check-in lists of an event, the live overview, and who may see them.
 */
final class DeskListsTest extends CheckInHttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->openDesk();
        $this->login(self::OWNER);
    }

    public function test_the_event_has_one_list_per_activity_with_its_counters(): void
    {
        $this->participant();

        $body = $this->request('GET', $this->teamPath('/lists'));

        self::assertResponseStatusCodeSame(200);
        $items = Json::arr($body, 'items');
        self::assertCount(2, $items);
        $byName = [];
        foreach ($items as $item) {
            $byName[Json::str($item, 'name')] = $item;
        }
        self::assertSame($this->trailList, Json::str($byName['Trail 5 km'], 'id'));
        self::assertSame($this->event['trail'], Json::str($byName['Trail 5 km'], 'activityId'));
        self::assertSame(1, Json::int($byName['Trail 5 km'], 'expected'));
        self::assertSame(0, Json::int($byName['Trail 5 km'], 'checkedIn'));
        self::assertSame(0, Json::int($byName['Dětský závod'], 'expected'));
        self::assertSame($this->kidsList, Json::str($byName['Dětský závod'], 'id'));
        self::assertSame('2026-11-21T09:00:00+00:00', Json::str($byName['Trail 5 km'], 'opensAt'));
    }

    public function test_the_overview_counts_expected_arrived_missing_unpaid_and_cash(): void
    {
        $arrived = $this->participant('Alena');
        $this->participant('Bedřich');
        $this->paidParticipant('Cecílie');
        $this->login(self::OWNER);
        $this->post($this->teamPath('/lists/'.$this->trailList.'/cash-check-ins'), ['participationId' => $arrived['participationId'], 'amountMinor' => 30000]);
        self::assertResponseStatusCodeSame(201, (string) $this->client->getResponse()->getContent());

        $overview = $this->request('GET', $this->teamPath('/overview'));

        self::assertResponseStatusCodeSame(200);
        self::assertSame($this->event['eventId'], Json::str($overview, 'eventId'));
        $trail = null;
        foreach (Json::arr($overview, 'lists') as $row) {
            if (Json::str($row, 'listId') === $this->trailList) {
                $trail = $row;
            }
        }
        self::assertNotNull($trail);
        self::assertSame(3, Json::int($trail, 'expected'));
        self::assertSame(1, Json::int($trail, 'checkedIn'));
        self::assertSame(2, Json::int($trail, 'missing'));
        self::assertSame(1, Json::int($trail, 'unpaid'), 'Bedřich has not paid; Alena paid cash at the door, Cecílie paid before');
        self::assertSame(30000, Json::int($trail, 'cashReceivedMinor'));
        self::assertSame(3, Json::int($overview, 'expected'));
        self::assertSame(1, Json::int($overview, 'checkedIn'));
        self::assertSame(2, Json::int($overview, 'missing'));
        self::assertSame(30000, Json::int($overview, 'cashReceivedMinor'));
    }

    public function test_a_voided_check_in_is_not_counted_as_arrived(): void
    {
        $participant = $this->paidParticipant();
        $this->login(self::OWNER);
        $checkIn = $this->post($this->teamPath('/lists/'.$this->trailList.'/check-ins'), ['participationId' => $participant['participationId']]);
        $this->request('POST', $this->teamPath('/check-ins/'.Json::str($checkIn, 'checkInId').'/void'), ['reason' => 'Wrong person']);
        self::assertResponseStatusCodeSame(204);

        $overview = $this->request('GET', $this->teamPath('/overview'));

        self::assertSame(0, Json::int($overview, 'checkedIn'));
        self::assertSame(1, Json::int($overview, 'missing'));
    }

    public function test_a_device_token_works_without_the_organizer_slug(): void
    {
        $token = $this->issueDeviceToken()['token'];

        $lists = $this->request('GET', $this->devicePath('/lists'), headers: self::bearer($token));
        self::assertResponseStatusCodeSame(200);
        self::assertCount(2, Json::arr($lists, 'items'));

        $this->request('GET', $this->devicePath('/overview'), headers: self::bearer($token));
        self::assertResponseStatusCodeSame(200);

        $this->request('GET', $this->teamPath('/lists'), headers: self::bearer($token));
        self::assertResponseStatusCodeSame(200, 'a device may also use the slug of its own organizer');
    }

    public function test_a_device_bound_to_one_list_sees_only_that_list(): void
    {
        $token = $this->issueDeviceToken($this->kidsList)['token'];

        $lists = $this->request('GET', $this->devicePath('/lists'), headers: self::bearer($token));
        $overview = $this->request('GET', $this->devicePath('/overview'), headers: self::bearer($token));

        self::assertCount(1, Json::arr($lists, 'items'));
        self::assertSame($this->kidsList, Json::str($lists, 'items.0.id'));
        self::assertCount(1, Json::arr($overview, 'lists'));
    }

    public function test_the_other_zone_of_a_device_is_not_open_to_the_slug_of_a_foreign_organizer(): void
    {
        $token = $this->issueDeviceToken()['token'];

        $this->request('GET', $this->teamPath('/lists', slug: 'other-club'), headers: self::bearer($token));

        $this->assertProblem(404, 'not_found');
    }

    public function test_anonymous_is_unauthorized(): void
    {
        $this->client->getCookieJar()->clear();

        $this->request('GET', $this->teamPath('/lists'));
        $this->assertProblem(401, 'unauthorized');

        $this->request('GET', $this->devicePath('/overview'));
        $this->assertProblem(401, 'unauthorized');
    }

    public function test_a_wrong_device_token_is_unauthorized(): void
    {
        $this->client->getCookieJar()->clear();

        $this->request('GET', $this->devicePath('/lists'), headers: self::bearer('dev1_0199a000-0000-7000-8000-000000000000_nope'));

        $this->assertProblem(401, 'invalid_device_token');
    }

    public function test_a_foreign_organizer_gets_not_found(): void
    {
        $this->signInAsForeignOwner();

        $this->request('GET', $this->teamPath('/lists'));
        $this->assertProblem(404, 'not_found');

        $this->request('GET', $this->teamPath('/overview'));
        $this->assertProblem(404, 'not_found');
    }

    public function test_a_member_restricted_to_another_event_is_forbidden(): void
    {
        $this->signInAs(Role::CheckIn, 'door@example.test');
        $membership = $this->dbString('SELECT m.id FROM identity.membership m JOIN identity.user_account u ON u.id = m.user_account_id WHERE u.email = ? AND m.organizer_id = ?', ['door@example.test', $this->owner['organizerId']]);
        $this->connection()->executeStatement(
            'INSERT INTO identity.membership_event_scope (membership_id, event_id, organizer_id) VALUES (?, ?, ?)',
            [$membership, '0199a000-0000-7000-8000-0000000000aa', $this->owner['organizerId']],
        );

        $this->request('GET', $this->teamPath('/lists'));

        $this->assertProblem(403, 'forbidden');
    }

    public function test_a_check_in_operator_sees_the_lists(): void
    {
        $this->signInAs(Role::CheckIn, 'door@example.test');

        $this->request('GET', $this->teamPath('/lists'));

        self::assertResponseStatusCodeSame(200);
    }

    public function test_a_route_id_that_is_not_a_uuid_is_not_found(): void
    {
        $this->request('GET', '/api/check-in/org/klub/events/not-a-uuid/lists');

        $this->assertProblem(404, 'not_found');
    }
}
