<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Registration;

use Eventor\Identity\Domain\Membership\Role;
use Eventor\Tests\Support\Catalog\Json;
use Eventor\Tests\Support\Registration\RegistrationHttpTestCase;

/**
 * The organizer's side: participant list and detail, order detail, REG-14 cancellation, CAP-05 restore, FRM-11 preview.
 */
final class AdminRegistrationTest extends RegistrationHttpTestCase
{
    /**
     * @return array{0: array{eventId: string, slug: string, trail: string, trailVariant: string, kids: ?string, kidsVariant: ?string}, 1: string, 2: string}
     */
    private function eventWithOrder(): array
    {
        $event = $this->openEvent();
        $this->publishAndOpen($event['eventId']);
        $placed = $this->placeOrder(self::orderBody($event));
        $this->login(self::OWNER);

        return [$event, Json::str($placed ?? [], 'orderId'), $this->dbString('SELECT id FROM registration.participation WHERE order_id = ?', [Json::str($placed ?? [], 'orderId')])];
    }

    public function test_the_participant_list_shows_the_dimensions_and_filters(): void
    {
        [$event, $orderId, $participationId] = $this->eventWithOrder();
        $this->placeOrder(self::orderBody($event, 'Petr', 'petr@example.test'));
        $this->login(self::OWNER);

        $list = $this->request('GET', self::BASE.'/events/'.$event['eventId'].'/participations');

        self::assertResponseIsSuccessful((string) $this->client->getResponse()->getContent());
        self::assertSame(2, Json::at($list ?? [], 'total'));
        $row = Json::at($list ?? [], 'items.0');
        self::assertSame($participationId, Json::str($row, 'id'));
        self::assertSame('Jana Nováková', Json::str($row, 'personName'));
        self::assertSame('Trail 5 km', Json::str($row, 'activityName'));
        self::assertSame('confirmed', Json::str($row, 'state'));
        self::assertSame('complete', Json::str($row, 'legalState'));
        self::assertSame('unpaid', Json::str($row, 'financialState'));
        self::assertSame($orderId, Json::str($row, 'orderId'));

        $found = $this->request('GET', self::BASE.'/events/'.$event['eventId'].'/participations?q=petr');
        self::assertSame(1, Json::at($found ?? [], 'total'));
        $none = $this->request('GET', self::BASE.'/events/'.$event['eventId'].'/participations?state=cancelled');
        self::assertSame(0, Json::at($none ?? [], 'total'));
        $this->request('GET', self::BASE.'/events/'.$event['eventId'].'/participations?state=bogus');
        $this->assertProblem(422, 'invalid_order_request');
    }

    public function test_detail_and_order_views(): void
    {
        [$event, $orderId, $participationId] = $this->eventWithOrder();

        $this->request('GET', self::BASE.'/events/'.$event['eventId'].'/participations/'.$participationId);
        self::assertResponseIsSuccessful();
        $order = $this->request('GET', self::BASE.'/events/'.$event['eventId'].'/orders/'.$orderId);
        self::assertResponseIsSuccessful();
        self::assertSame('jana@example.test', Json::str($order ?? [], 'ordererEmail'));
        self::assertSame(30000, Json::at($order ?? [], 'amountDue.minor'));
        self::assertCount(1, Json::arr($order ?? [], 'participations'));

        $this->request('GET', self::BASE.'/events/'.$event['eventId'].'/participations/0197a000-0000-7000-8000-000000000001');
        $this->assertProblem(404, 'participation_not_found');
        $this->request('GET', self::BASE.'/events/'.$event['eventId'].'/orders/0197a000-0000-7000-8000-000000000001');
        $this->assertProblem(404, 'order_not_found');
    }

    public function test_access_is_401_without_a_session_403_for_the_wrong_role_and_404_for_a_foreign_organizer(): void
    {
        [$event, , $participationId] = $this->eventWithOrder();
        $this->addMember('kasa@example.test', Role::Finance);
        $this->addMember('vstup@example.test', Role::CheckIn);
        $this->createOwner('cizi@example.test', 'cizi-klub');

        $this->client->getCookieJar()->clear();
        $this->request('GET', self::BASE.'/events/'.$event['eventId'].'/participations');
        $this->assertProblem(401, 'unauthorized');

        $this->login('vstup@example.test');
        $this->request('GET', self::BASE.'/events/'.$event['eventId'].'/participations');
        $this->assertProblem(403, 'forbidden');

        $this->client->getCookieJar()->clear();
        $this->login('kasa@example.test');
        $this->request('POST', self::BASE.'/events/'.$event['eventId'].'/participations/'.$participationId.'/cancel', ['reason' => 'x']);
        $this->assertProblem(403, 'forbidden');

        $this->client->getCookieJar()->clear();
        $this->login('cizi@example.test');
        $this->request('GET', self::BASE.'/events/'.$event['eventId'].'/participations');
        $this->assertProblem(404, 'not_found');
    }

    public function test_the_organizer_cancels_with_a_reason_and_the_seat_and_money_follow_reg14(): void
    {
        [$event, $orderId, $participationId] = $this->eventWithOrder();

        $this->request('POST', self::BASE.'/events/'.$event['eventId'].'/participations/'.$participationId.'/cancel', []);
        $this->assertProblem(422, 'invalid_order_request');

        $cancelled = $this->request('POST', self::BASE.'/events/'.$event['eventId'].'/participations/'.$participationId.'/cancel', ['reason' => 'Telefonicky odhlášen']);

        self::assertResponseIsSuccessful((string) $this->client->getResponse()->getContent());
        self::assertSame('cancelled', Json::str($cancelled ?? [], 'state'));
        self::assertSame(0, $this->dbInt("SELECT confirmed FROM registration.capacity_bucket WHERE scope_type = 'variant' AND scope_id = ?", [$event['trailVariant']]));
        self::assertSame(0, $this->dbInt('SELECT amount_due_minor FROM registration.orders WHERE id = ?', [$orderId]));
        self::assertSame('organizer', $this->dbString('SELECT cancelled_by FROM registration.participation WHERE id = ?', [$participationId]));
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM audit.audit_entry WHERE action = 'participation.state.cancel' AND entity_id = ? AND reason = 'Telefonicky odhlášen' AND actor_type = 'USER'", [$participationId]));
    }

    public function test_restore_of_a_confirmed_participation_is_a_409(): void
    {
        [$event, , $participationId] = $this->eventWithOrder();

        $this->request('POST', self::BASE.'/events/'.$event['eventId'].'/participations/'.$participationId.'/restore', ['reason' => 'zkouška']);

        $this->assertProblem(409, 'participation_transition_not_allowed');
    }

    public function test_the_form_preview_lists_the_fields_and_requirements_of_a_selection(): void
    {
        $event = $this->openEvent(kids: true);
        $this->publishAndOpen($event['eventId']);

        $preview = $this->request('POST', self::BASE.'/events/'.$event['eventId'].'/form-preview', ['items' => [
            ['itemRef' => 'a', 'activityId' => $event['trail'], 'variantId' => $event['trailVariant']],
            ['itemRef' => 'b', 'activityId' => (string) $event['kids'], 'variantId' => (string) $event['kidsVariant'], 'minor' => true],
        ]]);

        self::assertResponseIsSuccessful((string) $this->client->getResponse()->getContent());
        self::assertContains('first_name', Json::column(Json::at($preview ?? [], 'items.0.fields'), 'key'));
        self::assertContains('orderer_email', Json::column(Json::at($preview ?? [], 'orderFields'), 'key'));
        self::assertNotContains('orderer_email', Json::column(Json::at($preview ?? [], 'items.0.fields'), 'key'), 'order fields are asked once, not per person');
        self::assertNull($preview['formToken'] ?? null, 'the admin preview carries no antispam token');
    }

    public function test_the_form_preview_of_an_unpublished_event_is_a_404_and_needs_the_permission(): void
    {
        $event = $this->openEvent();
        $this->addMember('vstup@example.test', Role::CheckIn);

        $this->login(self::OWNER);
        $this->request('POST', self::BASE.'/events/'.$event['eventId'].'/form-preview', ['items' => [['activityId' => $event['trail'], 'variantId' => $event['trailVariant']]]]);
        self::assertResponseStatusCodeSame(404);

        $this->client->getCookieJar()->clear();
        $this->login('vstup@example.test');
        $this->request('POST', self::BASE.'/events/'.$event['eventId'].'/form-preview', ['items' => []]);
        $this->assertProblem(403, 'forbidden');
    }
}
