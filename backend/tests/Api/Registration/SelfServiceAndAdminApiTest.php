<?php

declare(strict_types=1);

namespace Eventor\Tests\Api\Registration;

use Eventor\Tests\Api\Support\ApiFixtures;
use Eventor\Tests\Support\Catalog\Json;

/**
 * Self-service (API-04, REG-08, REG-09) and the organizer's participant list (REG-14) over real HTTP: the full public flow
 * from the published event to the cancelled participation.
 */
final class SelfServiceAndAdminApiTest extends RegistrationApiTestCase
{
    public function test_place_read_correct_cancel_and_see_it_in_the_admin_list(): void
    {
        $event = $this->openEvent(limit: 5);
        $placed = $this->ok($this->place($event['slug'], self::orderBody($event)), 201);
        $auth = self::auth($placed);
        $self = $this->anonymous();

        // REG-08: the order behind the link
        $order = $this->ok($self->get('/api/self-service/order', $auth));
        self::assertSame(Json::str($placed, 'orderId'), Json::str($order, 'orderId'));
        self::assertSame(30000, Json::int($order, 'amountDue.minor'));
        $participation = Json::arr($order, 'participations.0');
        self::assertSame('confirmed', Json::str($participation, 'state'));
        self::assertStringStartsWith('qr1.', Json::str($participation, 'qrToken'));

        // REG-09: correct the data, then cancel
        $corrected = $self->request('PUT', '/api/self-service/persons/'.Json::str($participation, 'personId'), ['answers' => ['first_name' => 'Janička', 'last_name' => 'Nováková', 'birth_date' => '1990-05-04']], $auth);
        self::assertSame('Janička Nováková', Json::str($this->ok($corrected), 'participations.0.personName'));

        // the organizer sees the participant in the list
        $list = $this->ok($this->owner->get(self::ORG.'/events/'.$event['eventId'].'/participations'));
        self::assertSame(1, Json::int($list, 'total'));
        self::assertSame('Janička Nováková', Json::str($list, 'items.0.personName'));
        self::assertSame(Json::str($participation, 'id'), Json::str($list, 'items.0.id'));
        self::assertSame(Json::str($placed, 'orderId'), Json::str($this->ok($this->owner->get(self::ORG.'/events/'.$event['eventId'].'/orders/'.Json::str($placed, 'orderId'))), 'id'));
        self::assertSame(Json::str($participation, 'id'), Json::str($this->ok($this->owner->get(self::ORG.'/events/'.$event['eventId'].'/participations/'.Json::str($participation, 'id'))), 'id'));

        $cancelled = $this->ok($self->post('/api/self-service/participations/'.Json::str($participation, 'id').'/cancel', ['reason' => 'Nemoc'], $auth));
        self::assertSame('cancelled', Json::str($cancelled, 'participations.0.state'));
        self::assertSame(0, Json::int($cancelled, 'amountDue.minor'));
        self::assertNull(Json::at($cancelled, 'participations.0.qrToken'));
        $this->assertProblem($self->post('/api/self-service/participations/'.Json::str($participation, 'id').'/cancel', [], $auth), 409, 'participation_transition_not_allowed');

        $buckets = Json::arr($this->ok($this->owner->get(self::ORG.'/events/'.$event['eventId'].'/capacity')), 'buckets');
        $variants = array_values(array_filter($buckets, static fn(mixed $bucket): bool => \is_array($bucket) && 'variant' === Json::str($bucket, 'scope')));
        self::assertSame(0, Json::int($variants, '0.confirmed'), 'the seat is back');
    }

    public function test_the_organizer_cancels_with_a_reason(): void
    {
        $event = $this->openEvent();
        $placed = $this->ok($this->place($event['slug'], self::orderBody($event)), 201);
        $id = Json::str($this->ok($this->owner->get(self::ORG.'/events/'.$event['eventId'].'/orders/'.Json::str($placed, 'orderId'))), 'participations.0.id');

        $this->assertProblem($this->owner->post(self::ORG.'/events/'.$event['eventId'].'/participations/'.$id.'/cancel', []), 422, 'invalid_order_request');
        $cancelled = $this->ok($this->owner->post(self::ORG.'/events/'.$event['eventId'].'/participations/'.$id.'/cancel', ['reason' => 'Telefonicky odhlášen']));

        self::assertSame('cancelled', $cancelled['state']);
        $this->assertProblem($this->owner->post(self::ORG.'/events/'.$event['eventId'].'/participations/'.$id.'/restore', ['reason' => 'zkouška']), 409, 'participation_transition_not_allowed');
    }

    public function test_the_self_service_token_is_checked(): void
    {
        $event = $this->openEvent();
        $placed = $this->ok($this->place($event['slug'], self::orderBody($event)), 201);
        $client = $this->anonymous();

        $this->assertProblem($client->get('/api/self-service/order'), 401, 'token_invalid');
        $this->assertProblem($client->get('/api/self-service/order', ['Authorization' => 'SelfService ss1_00000000-0000-7000-8000-000000000000_'.str_repeat('A', 43)]), 401, 'token_invalid');
        $forged = preg_replace('/_[A-Za-z0-9_-]{43}$/', '_'.str_repeat('B', 43), self::auth($placed)['Authorization']);
        $this->assertProblem($client->get('/api/self-service/order', ['Authorization' => (string) $forged]), 401, 'token_invalid');
    }

    public function test_the_admin_endpoints_need_a_session_the_role_and_the_right_tenant(): void
    {
        $event = $this->openEvent();
        $placed = $this->ok($this->place($event['slug'], self::orderBody($event)), 201);
        $path = self::ORG.'/events/'.$event['eventId'].'/participations';

        $this->assertProblem($this->anonymous()->get($path), 401, 'unauthorized');

        $beta = $this->loginAs(ApiFixtures::BETA);
        $this->assertProblem($beta->get($path), 404, 'not_found');
        $this->assertProblem($beta->get(self::ORG.'/events/'.$event['eventId'].'/orders/'.Json::str($placed, 'orderId')), 404, 'not_found');
        $own = '/api/admin/org/'.ApiFixtures::BETA['slug'];
        $this->assertProblem($beta->get($own.'/events/'.$event['eventId'].'/orders/'.Json::str($placed, 'orderId')), 404, 'order_not_found');
    }

    public function test_the_form_preview_of_the_admin(): void
    {
        $event = $this->openEvent();

        $preview = $this->ok($this->owner->post(self::ORG.'/events/'.$event['eventId'].'/form-preview', ['items' => [['activityId' => $event['activityId'], 'variantId' => $event['variantId']]]]));

        self::assertContains('last_name', Json::column(Json::arr($preview, 'items.0.fields'), 'key'));
        $this->assertProblem($this->owner->post(self::ORG.'/events/0197a000-0000-7000-8000-000000000001/form-preview', ['items' => []]), 404, 'event_not_found');
        $this->assertProblem($this->anonymous()->post(self::ORG.'/events/'.$event['eventId'].'/form-preview', ['items' => []]), 401, 'unauthorized');
    }
}
