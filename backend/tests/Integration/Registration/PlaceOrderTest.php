<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Registration;

use Eventor\Tests\Support\Catalog\Json;
use Eventor\Tests\Support\Registration\RegistrationHttpTestCase;

/**
 * REG-01..03, REG-05, S01, S02: the public order endpoint end to end through the kernel.
 */
final class PlaceOrderTest extends RegistrationHttpTestCase
{
    public function test_a_cash_order_is_placed_confirmed_and_priced(): void
    {
        $event = $this->openEvent();
        $this->publishAndOpen($event['eventId']);

        $placed = $this->placeOrder(self::orderBody($event));

        self::assertResponseStatusCodeSame(201, (string) $this->client->getResponse()->getContent());
        self::assertIsArray($placed);
        self::assertMatchesRegularExpression('/^EV-\d{4}-000001$/', Json::str($placed, 'orderNumber'));
        self::assertSame('cash_on_site', Json::at($placed, 'payment.status'));
        self::assertStringContainsString('/s/ss1_', Json::str($placed, 'selfServiceUrl'));

        $orderId = Json::str($placed, 'orderId');
        self::assertSame(30000, $this->dbInt('SELECT amount_due_minor FROM registration.orders WHERE id = ?', [$orderId]));
        self::assertSame('confirmed', $this->dbString('SELECT state FROM registration.participation WHERE order_id = ?', [$orderId]));
        self::assertSame(1, $this->dbInt("SELECT confirmed FROM registration.capacity_bucket WHERE scope_type = 'variant' AND scope_id = ?", [$event['trailVariant']]));
        self::assertSame(0, $this->dbInt("SELECT held FROM registration.capacity_bucket WHERE scope_type = 'variant' AND scope_id = ?", [$event['trailVariant']]));
        self::assertSame('unpaid', $this->dbString('SELECT state FROM payments.order_finance WHERE order_id = ?', [$orderId]));
        self::assertSame(2, $this->dbInt('SELECT count(*) FROM registration.answer_set WHERE order_id = ?', [$orderId]));
        self::assertSame('Jana', $this->dbString('SELECT first_name FROM registration.person WHERE order_id = ?', [$orderId]));
        self::assertSame('jana@example.test', $this->dbString('SELECT orderer_email FROM registration.orders WHERE id = ?', [$orderId]));
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM audit.audit_entry WHERE action = 'order.placed' AND entity_id = ?", [$orderId]));
    }

    public function test_a_payment_method_the_organizer_did_not_enable_is_refused(): void
    {
        $event = $this->openEvent();
        $this->publishAndOpen($event['eventId']);

        $this->placeOrder(self::orderBody($event, overrides: ['paymentMethod' => 'bank_transfer']));
        $this->assertProblem(422, 'payment_method_not_allowed');
    }

    public function test_a_free_order_needs_no_payment_method(): void
    {
        $event = $this->openEvent(trailPrice: 0);
        $this->publishAndOpen($event['eventId']);

        $placed = $this->placeOrder(self::orderBody($event, overrides: ['paymentMethod' => null]));

        self::assertResponseStatusCodeSame(201, (string) $this->client->getResponse()->getContent());
        self::assertSame('not_required', Json::at($placed ?? [], 'payment.status'));
        self::assertSame('free', $this->dbString('SELECT payment_method FROM registration.orders WHERE id = ?', [Json::str($placed ?? [], 'orderId')]));
    }

    public function test_the_last_seat_goes_to_one_order_and_the_other_gets_409_without_a_trace_s01(): void
    {
        $event = $this->openEvent(trailLimit: 1);
        $this->publishAndOpen($event['eventId']);

        $this->placeOrder(self::orderBody($event, 'Jana', 'jana@example.test'));
        self::assertResponseStatusCodeSame(201);
        $this->placeOrder(self::orderBody($event, 'Petr', 'petr@example.test'));

        $problem = $this->assertProblem(409, 'capacity_exhausted');
        self::assertSame('variant', Json::at($problem, 'context.scope'));
        self::assertSame(1, $this->dbInt('SELECT count(*) FROM registration.orders WHERE event_id = ?', [$event['eventId']]));
        self::assertSame(1, $this->dbInt('SELECT count(*) FROM registration.participation WHERE event_id = ?', [$event['eventId']]));
        self::assertSame(1, $this->dbInt("SELECT confirmed + held FROM registration.capacity_bucket WHERE scope_type = 'variant' AND scope_id = ?", [$event['trailVariant']]));
    }

    public function test_an_order_across_a_full_and_a_free_activity_leaves_nothing_behind_s02(): void
    {
        $event = $this->openEvent(trailLimit: 1, kids: true);
        $this->publishAndOpen($event['eventId']);
        $this->placeOrder(self::orderBody($event, 'Jana'));
        self::assertResponseStatusCodeSame(201);

        $body = self::orderBody($event, 'Petr', 'petr@example.test', overrides: [
            'items' => [
                ['personRef' => 'p1', 'activityId' => (string) $event['kids'], 'variantId' => (string) $event['kidsVariant']],
                ['personRef' => 'p2', 'activityId' => $event['trail'], 'variantId' => $event['trailVariant']],
            ],
            'persons' => [
                ['personRef' => 'p1', 'answers' => ['first_name' => 'Kuba', 'last_name' => 'Novák', 'birth_date' => '2018-01-02', 'guardian_first_name' => 'Karel', 'guardian_last_name' => 'Novák', 'guardian_email' => 'karel@example.test']],
                ['personRef' => 'p2', 'answers' => ['first_name' => 'Petr', 'last_name' => 'Novák', 'birth_date' => '1985-01-02']],
            ],
        ]);
        $this->placeOrder($body);

        $this->assertProblem(409, 'capacity_exhausted');
        self::assertSame(0, $this->dbInt("SELECT confirmed + held FROM registration.capacity_bucket WHERE scope_type = 'variant' AND scope_id = ?", [(string) $event['kidsVariant']]), 'the free activity must not keep a seat');
        self::assertSame(1, $this->dbInt('SELECT count(*) FROM registration.orders WHERE event_id = ?', [$event['eventId']]));
    }

    public function test_the_same_idempotency_key_returns_the_first_order(): void
    {
        $event = $this->openEvent();
        $this->publishAndOpen($event['eventId']);
        $body = self::orderBody($event);

        $first = $this->placeOrder($body, 'key-0123456789abcdef');
        $second = $this->placeOrder($body, 'key-0123456789abcdef');

        self::assertSame(Json::str($first ?? [], 'orderId'), Json::str($second ?? [], 'orderId'));
        self::assertSame(1, $this->dbInt('SELECT count(*) FROM registration.orders WHERE event_id = ?', [$event['eventId']]));

        $this->placeOrder(self::orderBody($event, 'Petr'), 'key-0123456789abcdef');
        $this->assertProblem(409, 'idempotency_key_reused');
    }

    public function test_the_idempotency_key_is_mandatory(): void
    {
        $event = $this->openEvent();
        $this->publishAndOpen($event['eventId']);

        $this->client->getCookieJar()->clear();
        $this->request('POST', self::PUBLIC.'vikend/orders', self::orderBody($event));

        $this->assertProblem(400, 'idempotency_key_required');
    }

    public function test_invalid_answers_are_a_422_with_the_path_of_every_field(): void
    {
        $event = $this->openEvent();
        $this->publishAndOpen($event['eventId']);

        $this->placeOrder(self::orderBody($event, overrides: [
            'persons' => [['personRef' => 'p1', 'answers' => ['first_name' => 'Jana']]],
            'orderAnswers' => ['orderer_email' => 'not-an-email'],
        ]));

        $problem = $this->assertProblem(422, 'form_invalid');
        $paths = Json::column(Json::at($problem, 'violations'), 'path');
        self::assertContains('p1.last_name', $paths);
        self::assertContains('p1.birth_date', $paths);
        self::assertContains('order.orderer_email', $paths);
        self::assertSame(0, $this->dbInt('SELECT count(*) FROM registration.orders WHERE event_id = ?', [$event['eventId']]));
    }

    public function test_the_structure_of_the_request_is_checked(): void
    {
        $event = $this->openEvent();
        $this->publishAndOpen($event['eventId']);

        $this->placeOrder(self::orderBody($event, overrides: ['items' => [['personRef' => 'ghost', 'activityId' => $event['trail'], 'variantId' => $event['trailVariant']]]]));

        $problem = $this->assertProblem(422, 'invalid_order_request');
        self::assertContains('unknown_person', Json::column(Json::at($problem, 'violations'), 'code'));
    }

    public function test_a_participant_below_the_age_limit_is_not_eligible(): void
    {
        $event = $this->openEvent(kids: true);
        $this->publishAndOpen($event['eventId']);

        $this->placeOrder(self::orderBody($event, overrides: [
            'items' => [['personRef' => 'p1', 'activityId' => (string) $event['kids'], 'variantId' => (string) $event['kidsVariant']]],
            'persons' => [['personRef' => 'p1', 'answers' => ['first_name' => 'Karel', 'last_name' => 'Starý', 'birth_date' => '1960-01-02', 'guardian_first_name' => 'Karel', 'guardian_last_name' => 'Novák', 'guardian_email' => 'karel@example.test']]],
        ]));

        $this->assertProblem(422, 'not_eligible');
    }

    public function test_registration_that_is_not_open_is_a_409(): void
    {
        $event = $this->openEvent();
        $this->publish($event['eventId']);

        $this->placeOrder(self::orderBody($event));

        $this->assertProblem(409, 'registration_closed');
    }

    public function test_an_unknown_event_or_organizer_is_a_404(): void
    {
        $this->openEvent();

        $this->placeOrder(self::orderBody(['eventId' => '', 'slug' => '', 'trail' => '0197a000-0000-7000-8000-000000000001', 'trailVariant' => '0197a000-0000-7000-8000-000000000002', 'kids' => null, 'kidsVariant' => null]), slug: 'nope');
        $this->assertProblem(404, 'event_not_found');

        $this->client->getCookieJar()->clear();
        $this->request('POST', '/api/public/orgs/unknown/events/vikend/orders', [], ['Idempotency-Key' => bin2hex(random_bytes(12))]);
        $this->assertProblem(404, 'not_found');
    }

    public function test_the_honeypot_rejects_a_bot(): void
    {
        $event = $this->openEvent();
        $this->publishAndOpen($event['eventId']);

        $this->placeOrder(self::orderBody($event, overrides: ['website' => 'http://spam.example']));

        $this->assertProblem(422, 'spam_suspected');
        self::assertSame(0, $this->dbInt('SELECT count(*) FROM registration.orders WHERE event_id = ?', [$event['eventId']]));
    }

    public function test_a_form_submitted_too_fast_is_rejected_and_a_forged_token_too(): void
    {
        $event = $this->openEvent();
        $this->publishAndOpen($event['eventId']);

        $form = $this->request('POST', self::PUBLIC.'vikend/registration-form', ['items' => [['activityId' => $event['trail'], 'variantId' => $event['trailVariant']]]]);
        self::assertResponseIsSuccessful((string) $this->client->getResponse()->getContent());
        $token = Json::str($form ?? [], 'formToken');

        $this->placeOrder(self::orderBody($event, overrides: ['formToken' => $token]));
        $this->assertProblem(422, 'spam_suspected');

        $this->placeOrder(self::orderBody($event, overrides: ['formToken' => '1.deadbeef']));
        $this->assertProblem(422, 'spam_suspected');
    }

    public function test_the_order_number_counts_per_organizer_and_year(): void
    {
        $event = $this->openEvent();
        $this->publishAndOpen($event['eventId']);

        $first = $this->placeOrder(self::orderBody($event, 'Jana', 'a@example.test'));
        $second = $this->placeOrder(self::orderBody($event, 'Petr', 'b@example.test'));

        self::assertStringEndsWith('000001', Json::str($first ?? [], 'orderNumber'));
        self::assertStringEndsWith('000002', Json::str($second ?? [], 'orderNumber'));
    }
}
