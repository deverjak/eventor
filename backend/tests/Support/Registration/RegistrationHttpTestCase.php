<?php

declare(strict_types=1);

namespace Eventor\Tests\Support\Registration;

use Eventor\Tests\Support\Catalog\CatalogHttpTestCase;
use Eventor\Tests\Support\Catalog\Json;

/**
 * Base of the Registration HTTP integration tests: an organizer "klub", a published event with an open registration
 * (built through the admin API like a real organizer would) and helpers that place orders through the public endpoint.
 */
abstract class RegistrationHttpTestCase extends CatalogHttpTestCase
{
    protected const string PUBLIC = '/api/public/orgs/klub/events/';

    /**
     * A published, open event: "Trail 5 km" (variant "Jednotlivec", price 300 Kč) and, when `$kids`, "Dětský závod"
     * (for 4 to 14 years, price 100 Kč). Payment: cash (or the given methods). The default form (first name, last name,
     * birth date, order contact).
     *
     * @param list<string> $methods
     *
     * @return array{eventId: string, slug: string, trail: string, trailVariant: string, kids: ?string, kidsVariant: ?string}
     */
    protected function openEvent(string $slug = 'vikend', ?int $trailLimit = 100, array $methods = ['cash'], bool $kids = false, int $trailPrice = 30000): array
    {
        $this->login(self::OWNER);
        $created = $this->createEvent(slug: $slug);
        $eventId = Json::str($created, 'eventId');
        $trail = $this->addActivity($eventId, 'Trail 5 km', ['startsAt' => '2026-11-21T10:00:00+01:00']);
        $trailVariant = $this->addVariant($eventId, $trail, 'Jednotlivec', $trailPrice, $trailLimit);
        $kidsId = null;
        $kidsVariant = null;
        if ($kids) {
            $kidsId = $this->addActivity($eventId, 'Dětský závod', ['startsAt' => '2026-11-22T10:00:00+01:00', 'minorsOnly' => true, 'maxAge' => 14]);
            $kidsVariant = $this->addVariant($eventId, $kidsId, 'Dítě', 10000, 50);
            // A children's activity publishes only with the guardian fields in the form (LEG-11).
            $this->request('PUT', self::BASE.'/events/'.$eventId.'/form/draft', ['fields' => [
                ['key' => 'first_name', 'type' => 'short_text', 'label' => 'Jméno', 'scope' => 'person', 'required' => true, 'semantic' => 'first_name'],
                ['key' => 'last_name', 'type' => 'short_text', 'label' => 'Příjmení', 'scope' => 'person', 'required' => true, 'semantic' => 'last_name'],
                ['key' => 'birth_date', 'type' => 'date', 'label' => 'Datum narození', 'scope' => 'person', 'required' => true, 'semantic' => 'birth_date'],
                ['key' => 'guardian_first_name', 'type' => 'short_text', 'label' => 'Jméno zástupce', 'scope' => 'person', 'required' => true, 'semantic' => 'guardian_first_name', 'activityIds' => [$kidsId]],
                ['key' => 'guardian_last_name', 'type' => 'short_text', 'label' => 'Příjmení zástupce', 'scope' => 'person', 'required' => true, 'semantic' => 'guardian_last_name', 'activityIds' => [$kidsId]],
                ['key' => 'guardian_email', 'type' => 'email', 'label' => 'E-mail zástupce', 'scope' => 'person', 'required' => true, 'semantic' => 'guardian_email', 'activityIds' => [$kidsId]],
                ['key' => 'orderer_first_name', 'type' => 'short_text', 'label' => 'Jméno', 'scope' => 'order', 'required' => true, 'semantic' => 'first_name'],
                ['key' => 'orderer_last_name', 'type' => 'short_text', 'label' => 'Příjmení', 'scope' => 'order', 'required' => true, 'semantic' => 'last_name'],
                ['key' => 'orderer_email', 'type' => 'email', 'label' => 'E-mail', 'scope' => 'order', 'required' => true, 'semantic' => 'email'],
            ]]);
            self::assertResponseIsSuccessful((string) $this->client->getResponse()->getContent());
        }
        $this->request('PUT', self::BASE.'/events/'.$eventId.'/payment-rules', ['methods' => $methods, 'dueDays' => 7, 'holdTtlHours' => 48]);
        self::assertResponseIsSuccessful((string) $this->client->getResponse()->getContent());

        return ['eventId' => $eventId, 'slug' => $slug, 'trail' => $trail, 'trailVariant' => $trailVariant, 'kids' => $kidsId, 'kidsVariant' => $kidsVariant];
    }

    protected function publishAndOpen(string $eventId): void
    {
        $this->publish($eventId);
        $this->request('POST', self::BASE.'/events/'.$eventId.'/registration/open', ['cascade' => true]);
        self::assertResponseIsSuccessful((string) $this->client->getResponse()->getContent());
    }

    /**
     * The body of a one-person order for the "Jednotlivec" variant of the trail.
     *
     * @param array{eventId: string, slug: string, trail: string, trailVariant: string, kids: ?string, kidsVariant: ?string} $event
     * @param array<string, mixed>                                                                                            $overrides
     *
     * @return array<string, mixed>
     */
    protected static function orderBody(array $event, string $first = 'Jana', string $email = 'jana@example.test', string $birth = '1990-05-04', array $overrides = []): array
    {
        return $overrides + [
            'items' => [['personRef' => 'p1', 'activityId' => $event['trail'], 'variantId' => $event['trailVariant']]],
            'persons' => [['personRef' => 'p1', 'answers' => ['first_name' => $first, 'last_name' => 'Nováková', 'birth_date' => $birth]]],
            'orderAnswers' => ['orderer_first_name' => 'Jana', 'orderer_last_name' => 'Nováková', 'orderer_email' => $email],
            'paymentMethod' => 'cash',
        ];
    }

    /**
     * Places an order through the public endpoint with a fresh idempotency key; the cookie jar is cleared first, the
     * participant has no session.
     *
     * @param array<string, mixed> $body
     *
     * @return array<array-key, mixed>|null
     */
    protected function placeOrder(array $body, ?string $key = null, string $slug = 'vikend'): ?array
    {
        $this->client->getCookieJar()->clear();

        return $this->request('POST', self::PUBLIC.$slug.'/orders', $body, ['Idempotency-Key' => $key ?? bin2hex(random_bytes(12))]);
    }

    /**
     * @param array<array-key, mixed> $placed the 201 response of an order
     *
     * @return array<string, string> headers of the self-service call
     */
    protected static function selfService(array $placed): array
    {
        $url = Json::str($placed, 'selfServiceUrl');
        self::assertSame(1, preg_match('#/s/(ss1_[^/]+)$#', $url, $m), $url);

        return ['Authorization' => 'SelfService '.$m[1]];
    }
}
