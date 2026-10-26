<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Registration;

use Eventor\Tests\Support\Catalog\Json;
use Eventor\Tests\Support\Registration\RegistrationHttpTestCase;

/**
 * API-04 and REG-08, REG-09, REG-11: the participant behind the self-service token.
 */
final class SelfServiceTest extends RegistrationHttpTestCase
{
    /**
     * @return array{0: array{eventId: string, slug: string, trail: string, trailVariant: string, kids: ?string, kidsVariant: ?string}, 1: array<array-key, mixed>, 2: array<string, string>}
     */
    private function placed(int $trailLimit = 100): array
    {
        $event = $this->openEvent(trailLimit: $trailLimit);
        $this->publishAndOpen($event['eventId']);
        $placed = $this->placeOrder(self::orderBody($event));
        self::assertIsArray($placed);

        return [$event, $placed, self::selfService($placed)];
    }

    public function test_the_order_is_readable_with_the_token_and_carries_the_qr_code(): void
    {
        [, $placed, $auth] = $this->placed();

        $order = $this->request('GET', '/api/self-service/order', null, $auth);

        self::assertResponseIsSuccessful((string) $this->client->getResponse()->getContent());
        self::assertSame(Json::str($placed, 'orderId'), Json::str($order ?? [], 'orderId'));
        self::assertSame('Jana Nováková', Json::str($order ?? [], 'ordererName'));
        self::assertSame(30000, Json::at($order ?? [], 'amountDue.minor'));
        self::assertSame('unpaid', Json::str($order ?? [], 'financialState'));
        self::assertSame('Trail 5 km', Json::at($order ?? [], 'participations.0.activityName'));
        self::assertSame('confirmed', Json::at($order ?? [], 'participations.0.state'));
        self::assertStringStartsWith('qr1.', Json::str(Json::at($order ?? [], 'participations.0'), 'qrToken'));
        self::assertTrue(Json::at($order ?? [], 'participations.0.canCancel'));
    }

    public function test_the_link_is_the_one_communication_can_rebuild(): void
    {
        [, $placed] = $this->placed();

        $links = $this->service(\Eventor\Registration\Contract\SelfServiceLinks::class);
        $organizer = \Eventor\Shared\Domain\Id\OrganizerId::fromString($this->owner['organizerId']);

        self::assertSame(Json::str($placed, 'selfServiceUrl'), $links->urlFor($organizer, \Eventor\Shared\Domain\Id\OrderId::fromString(Json::str($placed, 'orderId'))));
    }

    public function test_a_missing_wrong_or_revoked_token_is_a_401(): void
    {
        [, $placed, $auth] = $this->placed();

        $this->request('GET', '/api/self-service/order');
        $this->assertProblem(401, 'token_invalid');

        $this->request('GET', '/api/self-service/order', null, ['Authorization' => 'SelfService nonsense']);
        $this->assertProblem(401, 'token_invalid');

        $forged = preg_replace('/_[A-Za-z0-9_-]{43}$/', '_'.str_repeat('A', 43), $auth['Authorization']);
        $this->request('GET', '/api/self-service/order', null, ['Authorization' => (string) $forged]);
        $this->assertProblem(401, 'token_invalid');

        $this->connection()->executeStatement('UPDATE registration.self_service_token SET revoked_at = now() WHERE order_id = ?', [Json::str($placed, 'orderId')]);
        $this->request('GET', '/api/self-service/order', null, $auth);
        $problem = $this->assertProblem(401, 'token_invalid');
        self::assertSame('recover_self_service_link', Json::at($problem, 'nextSteps.0.action'));
    }

    public function test_cancelling_frees_the_seat_reduces_the_amount_and_kills_the_qr(): void
    {
        [$event, $placed, $auth] = $this->placed(trailLimit: 1);
        $order = $this->request('GET', '/api/self-service/order', null, $auth);
        $participationId = Json::str(Json::at($order ?? [], 'participations.0'), 'id');

        $after = $this->request('POST', '/api/self-service/participations/'.$participationId.'/cancel', ['reason' => 'Nemoc'], $auth);

        self::assertResponseIsSuccessful((string) $this->client->getResponse()->getContent());
        self::assertSame('cancelled', Json::at($after ?? [], 'participations.0.state'));
        self::assertNull(Json::at($after ?? [], 'participations.0.qrToken'));
        self::assertSame(0, Json::at($after ?? [], 'amountDue.minor'));
        self::assertSame(0, $this->dbInt("SELECT confirmed FROM registration.capacity_bucket WHERE scope_type = 'variant' AND scope_id = ?", [$event['trailVariant']]));
        self::assertSame(2, $this->dbInt('SELECT qr_version FROM registration.participation WHERE id = ?', [$participationId]));
        self::assertSame('participant', $this->dbString('SELECT cancelled_by FROM registration.participation WHERE id = ?', [$participationId]));

        // the seat can be taken again
        $this->placeOrder(self::orderBody($event, 'Petr', 'petr@example.test'));
        self::assertResponseStatusCodeSame(201);

        // a second cancellation is a conflict
        $this->request('POST', '/api/self-service/participations/'.$participationId.'/cancel', [], $auth);
        $this->assertProblem(409, 'participation_transition_not_allowed');
    }

    public function test_a_participation_of_another_order_is_a_404(): void
    {
        [$event, , $auth] = $this->placed();
        $other = $this->placeOrder(self::orderBody($event, 'Petr', 'petr@example.test'));
        $foreign = $this->dbString('SELECT id FROM registration.participation WHERE order_id = ?', [Json::str($other ?? [], 'orderId')]);

        $this->request('POST', '/api/self-service/participations/'.$foreign.'/cancel', [], $auth);

        $this->assertProblem(404, 'participation_not_found');
    }

    public function test_cancellation_can_be_switched_off_by_the_organizer(): void
    {
        $event = $this->openEvent();
        $this->request('PUT', self::BASE.'/events/'.$event['eventId'].'/payment-rules', ['methods' => ['cash'], 'selfServiceCancellationAllowed' => false]);
        $this->publishAndOpen($event['eventId']);
        $placed = $this->placeOrder(self::orderBody($event));
        $auth = self::selfService($placed ?? []);
        $order = $this->request('GET', '/api/self-service/order', null, $auth);
        self::assertFalse(Json::at($order ?? [], 'participations.0.canCancel'));

        $this->request('POST', '/api/self-service/participations/'.Json::str(Json::at($order ?? [], 'participations.0'), 'id').'/cancel', [], $auth);

        $this->assertProblem(409, 'self_service_action_not_allowed');
    }

    public function test_the_participant_corrects_their_data(): void
    {
        [, , $auth] = $this->placed();
        $order = $this->request('GET', '/api/self-service/order', null, $auth);
        $personId = Json::str(Json::at($order ?? [], 'participations.0'), 'personId');

        $this->request('PUT', '/api/self-service/persons/'.$personId, ['answers' => ['first_name' => 'Janička', 'last_name' => 'Nováková', 'birth_date' => '1991-06-07']], $auth);

        self::assertResponseIsSuccessful((string) $this->client->getResponse()->getContent());
        self::assertSame('Janička', $this->dbString('SELECT first_name FROM registration.person WHERE id = ?', [$personId]));
        self::assertSame('1991-06-07', $this->dbString('SELECT birth_date::text FROM registration.person WHERE id = ?', [$personId]));
        self::assertSame(2, $this->dbInt('SELECT count(*) FROM registration.answer_set WHERE scope = \'person\' AND subject_id = ?', [$personId]));
        self::assertSame(1, $this->dbInt('SELECT count(*) FROM registration.answer_set WHERE scope = \'person\' AND subject_id = ? AND superseded_by_id IS NULL', [$personId]));
        $audit = $this->dbString("SELECT after::text FROM audit.audit_entry WHERE action = 'person.data_corrected' AND entity_id = ?", [$personId]);
        self::assertStringContainsString('first_name', $audit);
        self::assertStringNotContainsString('Janička', $audit, 'the audit names fields, never values');
        self::assertStringContainsString('PARTICIPANT_TOKEN', $this->dbString("SELECT actor_type FROM audit.audit_entry WHERE action = 'person.data_corrected' AND entity_id = ?", [$personId]));
    }

    public function test_an_invalid_correction_is_a_422_and_another_orders_person_a_404(): void
    {
        [$event, , $auth] = $this->placed();
        $order = $this->request('GET', '/api/self-service/order', null, $auth);
        $personId = Json::str(Json::at($order ?? [], 'participations.0'), 'personId');

        $this->request('PUT', '/api/self-service/persons/'.$personId, ['answers' => ['first_name' => 'X']], $auth);
        $this->assertProblem(422, 'form_invalid');

        $other = $this->placeOrder(self::orderBody($event, 'Petr', 'petr@example.test'));
        $foreignPerson = $this->dbString('SELECT id FROM registration.person WHERE order_id = ?', [Json::str($other ?? [], 'orderId')]);
        $this->request('PUT', '/api/self-service/persons/'.$foreignPerson, ['answers' => ['first_name' => 'Petr', 'last_name' => 'N', 'birth_date' => '1990-01-01']], $auth);
        $this->assertProblem(404, 'person_not_found');
    }

    public function test_the_orderer_fixes_the_contact(): void
    {
        [, $placed, $auth] = $this->placed();

        $this->request('PUT', '/api/self-service/contact', ['answers' => ['orderer_first_name' => 'Jana', 'orderer_last_name' => 'Nováková', 'orderer_email' => 'spravne@example.test']], $auth);

        self::assertResponseIsSuccessful((string) $this->client->getResponse()->getContent());
        self::assertSame('spravne@example.test', $this->dbString('SELECT orderer_email FROM registration.orders WHERE id = ?', [Json::str($placed, 'orderId')]));
    }

    public function test_the_link_recovery_answers_202_even_when_the_limit_is_hit(): void
    {
        $this->placed();
        $this->client->getCookieJar()->clear();

        for ($i = 0; $i < 15; ++$i) {
            $response = $this->request('POST', '/api/public/self-service/recover', ['email' => 'jana@example.test', 'orgSlug' => 'klub']);
            self::assertResponseStatusCodeSame(202, 'attempt '.$i);
            self::assertSame(['status' => 'accepted'], $response);
        }
    }

    public function test_the_link_recovery_always_answers_202_and_announces_the_link(): void
    {
        [$event, $placed] = $this->placed();
        $this->client->getCookieJar()->clear();

        $this->request('POST', '/api/public/self-service/recover', ['email' => 'unknown@example.test', 'orgSlug' => 'klub']);
        self::assertResponseStatusCodeSame(202);

        $this->request('POST', '/api/public/self-service/recover', ['email' => 'JANA@example.test', 'orgSlug' => 'klub', 'eventSlug' => $event['slug']]);
        self::assertResponseStatusCodeSame(202);
        self::assertSame(1, $this->dbInt('SELECT count(*) FROM registration.self_service_token WHERE order_id = ?', [Json::str($placed, 'orderId')]), 'the existing usable token is reused');

        $this->request('POST', '/api/public/self-service/recover', ['email' => 'x@example.test', 'orgSlug' => 'no-such-club']);
        self::assertResponseStatusCodeSame(202);
        self::assertNotSame('', Json::str($placed, 'orderId'));
    }
}
