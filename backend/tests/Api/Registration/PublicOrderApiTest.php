<?php

declare(strict_types=1);

namespace Eventor\Tests\Api\Registration;

use Eventor\Tests\Api\Support\ApiFixtures;
use Eventor\Tests\Support\Catalog\Json;

/**
 * The public flow over real HTTP: the form, the order, its errors (API-03: antispam, validation, capacity, idempotency).
 * The order endpoint is rate limited per address; the API stack raises the limits through ORDER_MAX_PER_10_MIN and
 * ORDER_MAX_PER_MIN_EVENT, the 429 itself is proven at integration level.
 */
final class PublicOrderApiTest extends RegistrationApiTestCase
{
    public function test_the_form_for_a_selection_then_the_order_then_the_same_key_again(): void
    {
        $event = $this->openEvent();

        $form = $this->ok($this->anonymous()->post(self::PUBLIC.$event['slug'].'/registration-form', ['items' => [['itemRef' => 'p1', 'activityId' => $event['activityId'], 'variantId' => $event['variantId']]]]));
        self::assertNotEmpty(Json::str($form, 'formToken'));
        self::assertContains('first_name', Json::column(Json::arr($form, 'items.0.fields'), 'key'));
        self::assertContains('orderer_email', Json::column(Json::arr($form, 'orderFields'), 'key'));

        $response = $this->place($event['slug'], self::orderBody($event), 'api-key-0123456789abcdef');
        $placed = $this->ok($response, 201);
        self::assertMatchesRegularExpression('/^EV-\d{4}-\d{6}$/', self::str($placed, 'orderNumber'));
        self::assertSame('cash_on_site', Json::str($placed, 'payment.status'));

        $again = $this->ok($this->place($event['slug'], self::orderBody($event), 'api-key-0123456789abcdef'), 201);
        self::assertSame(Json::str($placed, 'orderId'), Json::str($again, 'orderId'), 'the same key and body return the stored answer');

        $this->assertProblem($this->place($event['slug'], self::orderBody($event, 'Petr'), 'api-key-0123456789abcdef'), 409, 'idempotency_key_reused');
    }

    public function test_the_last_seat_goes_to_one_order_and_the_next_gets_409(): void
    {
        $event = $this->openEvent(limit: 1);

        $this->ok($this->place($event['slug'], self::orderBody($event)), 201);
        $problem = $this->assertProblem($this->place($event['slug'], self::orderBody($event, 'Petr', 'petr@example.test')), 409, 'capacity_exhausted');

        self::assertSame('variant', Json::str($problem, 'context.scope'));
        $buckets = $this->ok($this->owner->get(self::ORG.'/events/'.$event['eventId'].'/capacity'));
        $variants = array_values(array_filter(Json::arr($buckets, 'buckets'), static fn(mixed $bucket): bool => \is_array($bucket) && 'variant' === Json::str($bucket, 'scope')));
        self::assertSame(1, Json::int($variants, '0.confirmed'));
        self::assertSame(0, Json::int($variants, '0.available'));
    }

    public function test_validation_antispam_and_lookup_errors(): void
    {
        $event = $this->openEvent();

        $invalid = $this->assertProblem($this->place($event['slug'], self::orderBody($event, overrides: ['persons' => [['personRef' => 'p1', 'answers' => ['first_name' => 'Jana']]]])), 422, 'form_invalid');
        self::assertContains('p1.last_name', Json::column(Json::arr($invalid, 'violations'), 'path'));

        $this->assertProblem($this->place($event['slug'], self::orderBody($event, overrides: ['website' => 'http://spam.example'])), 422, 'spam_suspected');

        $this->assertProblem($this->anonymous()->post(self::PUBLIC.$event['slug'].'/orders', self::orderBody($event)), 400, 'idempotency_key_required');

        $this->assertProblem($this->place('no-such-event', self::orderBody($event)), 404, 'event_not_found');
        $this->assertProblem($this->anonymous()->post('/api/public/orgs/nobody/events/'.$event['slug'].'/orders', self::orderBody($event), ['Idempotency-Key' => bin2hex(random_bytes(12))]), 404, 'not_found');
    }

    public function test_the_link_recovery_never_tells_who_registered(): void
    {
        $event = $this->openEvent();
        $this->ok($this->place($event['slug'], self::orderBody($event, email: 'obnova-'.bin2hex(random_bytes(3)).'@example.test')), 201);

        $known = $this->anonymous()->post('/api/public/self-service/recover', ['email' => 'jana@example.test', 'orgSlug' => ApiFixtures::ALPHA['slug'], 'eventSlug' => $event['slug']]);
        $unknown = $this->anonymous()->post('/api/public/self-service/recover', ['email' => 'nobody@example.test', 'orgSlug' => ApiFixtures::ALPHA['slug']]);

        self::assertSame(202, $known->status);
        self::assertSame(202, $unknown->status);
        self::assertSame($known->body, $unknown->body);
    }
}
