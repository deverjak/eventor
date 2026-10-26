<?php

declare(strict_types=1);

namespace Eventor\Tests\Api\Registration;

use Eventor\Tests\Api\Support\ApiClient;
use Eventor\Tests\Api\Support\ApiFixtures;
use Eventor\Tests\Api\Support\ApiResponse;
use Eventor\Tests\Api\Support\ApiTestCase;

/**
 * Helpers of the Registration black-box tests. Everything is created through the API of the seeded organizer "api-alpha"
 * with slugs unique per test, the way a real organizer and a real participant would: the admin zone with a session, the
 * public and self-service zones without.
 */
abstract class RegistrationApiTestCase extends ApiTestCase
{
    protected const string ORG = '/api/admin/org/'.ApiFixtures::ALPHA['slug'];
    protected const string PUBLIC = '/api/public/orgs/'.ApiFixtures::ALPHA['slug'].'/events/';

    protected ApiClient $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->loginAs(ApiFixtures::ALPHA);
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function ok(ApiResponse $response, int $status = 200): array
    {
        self::assertSame($status, $response->status, $response->body);

        return $response->json();
    }

    protected static function str(mixed $array, string $key): string
    {
        self::assertIsArray($array);
        self::assertArrayHasKey($key, $array);
        self::assertIsString($array[$key]);

        return $array[$key];
    }

    /**
     * A published event with an open registration: "Trail 5 km" with one variant (price 300 Kč, cash), optionally limited.
     *
     * @return array{eventId: string, slug: string, activityId: string, variantId: string}
     */
    protected function openEvent(?int $limit = null, int $priceMinor = 30000): array
    {
        $slug = 'reg-'.bin2hex(random_bytes(5));
        $created = $this->ok($this->owner->post(self::ORG.'/events', ['slug' => $slug, 'mode' => 'multi', 'details' => [
            'name' => 'Registrační test '.$slug,
            'startsAt' => '2027-06-12T08:00:00+02:00',
            'endsAt' => '2027-06-12T18:00:00+02:00',
            'timezone' => 'Europe/Prague',
            'venues' => [['name' => 'Borský park', 'address' => 'Plzeň', 'mapUrl' => 'https://mapy.cz/borsky-park']],
        ]]), 201);
        $eventId = self::str($created, 'eventId');
        $activityId = self::str($this->ok($this->owner->post(self::ORG.'/events/'.$eventId.'/activities', ['name' => 'Trail 5 km', 'startsAt' => '2027-06-12T10:00:00+02:00']), 201), 'activityId');
        $variantId = self::str($this->ok($this->owner->post(self::ORG.'/events/'.$eventId.'/activities/'.$activityId.'/variants', [
            'name' => 'Jednotlivec',
            'price' => ['minor' => $priceMinor, 'currency' => 'CZK'],
            'capacityLimit' => $limit,
        ]), 201), 'variantId');
        self::assertSame(200, $this->owner->request('PUT', self::ORG.'/events/'.$eventId.'/payment-rules', ['methods' => ['cash'], 'dueDays' => 7, 'holdTtlHours' => 48])->status);
        $this->ok($this->owner->post(self::ORG.'/events/'.$eventId.'/publish', []));
        $this->ok($this->owner->post(self::ORG.'/events/'.$eventId.'/registration/open', ['cascade' => true]));

        return ['eventId' => $eventId, 'slug' => $slug, 'activityId' => $activityId, 'variantId' => $variantId];
    }

    /**
     * @param array{eventId: string, slug: string, activityId: string, variantId: string} $event
     * @param array<string, mixed>                                                        $overrides
     *
     * @return array<string, mixed>
     */
    protected static function orderBody(array $event, string $first = 'Jana', string $email = 'jana@example.test', array $overrides = []): array
    {
        return $overrides + [
            'items' => [['personRef' => 'p1', 'activityId' => $event['activityId'], 'variantId' => $event['variantId']]],
            'persons' => [['personRef' => 'p1', 'answers' => ['first_name' => $first, 'last_name' => 'Nováková', 'birth_date' => '1990-05-04']]],
            'orderAnswers' => ['orderer_first_name' => 'Jana', 'orderer_last_name' => 'Nováková', 'orderer_email' => $email],
            'paymentMethod' => 'cash',
        ];
    }

    /**
     * @param array<string, mixed> $body
     */
    protected function place(string $slug, array $body, ?string $key = null): ApiResponse
    {
        return $this->anonymous()->post(self::PUBLIC.$slug.'/orders', $body, ['Idempotency-Key' => $key ?? bin2hex(random_bytes(12))]);
    }

    /**
     * @param array<array-key, mixed> $placed
     *
     * @return array<string, string>
     */
    protected static function auth(array $placed): array
    {
        self::assertSame(1, preg_match('#/s/(ss1_[^/]+)$#', self::str($placed, 'selfServiceUrl'), $m));

        return ['Authorization' => 'SelfService '.$m[1]];
    }
}
