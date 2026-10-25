<?php

declare(strict_types=1);

namespace Eventor\Tests\Api\Catalog;

use Eventor\Tests\Api\Support\ApiClient;
use Eventor\Tests\Api\Support\ApiFixtures;
use Eventor\Tests\Api\Support\ApiResponse;
use Eventor\Tests\Api\Support\ApiTestCase;

/**
 * Helpers of the Catalog black-box tests. Everything is created through the admin API of the seeded organizer
 * "api-alpha" with a slug that is unique per test, so tests never meet each other's data.
 */
abstract class CatalogApiTestCase extends ApiTestCase
{
    protected const string ORG = '/api/admin/org/'.ApiFixtures::ALPHA['slug'];

    protected ApiClient $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->loginAs(ApiFixtures::ALPHA);
    }

    protected static function uniqueSlug(string $prefix = 'vikend'): string
    {
        return $prefix.'-'.bin2hex(random_bytes(5));
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    protected static function details(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Borský podzimní víkend 2026',
            'shortDescription' => 'Podzimní závody v Borském parku',
            'description' => 'Sobota a neděle v Plzni: běh, štafeta, dětský závod a canicross.',
            'startsAt' => '2026-11-21T08:00:00+01:00',
            'endsAt' => '2026-11-22T16:00:00+01:00',
            'timezone' => 'Europe/Prague',
            'contactName' => 'Jana Nováková',
            'contactEmail' => 'jana@example.test',
            'venues' => [['name' => 'Borský park', 'address' => 'Plzeň', 'mapUrl' => 'https://mapy.cz/borsky-park']],
        ];
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function ok(ApiResponse $response, int $status = 200): array
    {
        self::assertSame($status, $response->status, $response->body);

        return $response->json();
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<array-key, mixed>
     */
    protected function createEvent(?string $slug = null, string $mode = 'multi', array $overrides = []): array
    {
        return $this->ok($this->owner->post(self::ORG.'/events', ['slug' => $slug ?? self::uniqueSlug(), 'mode' => $mode, 'details' => self::details($overrides)]), 201);
    }

    /**
     * @param array<string, mixed> $extra
     */
    protected function addActivity(string $eventId, string $name, array $extra = []): string
    {
        $body = $this->ok($this->owner->post(self::ORG.'/events/'.$eventId.'/activities', ['name' => $name] + $extra), 201);
        self::assertIsString($body['activityId']);

        return $body['activityId'];
    }

    protected function addVariant(string $eventId, string $activityId, string $name, int $priceMinor, ?int $capacity = null, ?int $teamSize = null): string
    {
        $body = $this->ok($this->owner->post(self::ORG.'/events/'.$eventId.'/activities/'.$activityId.'/variants', [
            'name' => $name,
            'price' => ['minor' => $priceMinor, 'currency' => 'CZK'],
            'capacityLimit' => $capacity,
            'teamSize' => $teamSize,
        ]), 201);
        self::assertIsString($body['variantId']);

        return $body['variantId'];
    }

    /**
     * The demo event of the specification (chapter 13) as far as Catalog is concerned: five activities with their
     * variants and capacities, cash payment, the form of the event.
     *
     * @return array{eventId: string, slug: string, activities: array<string, string>, variants: array<string, string>}
     */
    protected function buildDemoEvent(?string $slug = null): array
    {
        $created = $this->createEvent($slug);
        $eventId = (string) $created['eventId'];

        $activities = [
            'trail5' => $this->addActivity($eventId, 'Trail 5 km', ['startsAt' => '2026-11-21T10:00:00+01:00', 'capacityLimit' => 100]),
            'trail10' => $this->addActivity($eventId, 'Trail 10 km', ['startsAt' => '2026-11-21T11:00:00+01:00', 'minAge' => 15, 'capacityLimit' => 150]),
            'relay' => $this->addActivity($eventId, 'Týmová štafeta', ['startsAt' => '2026-11-21T14:00:00+01:00']),
            'kids' => $this->addActivity($eventId, 'Dětský závod', ['startsAt' => '2026-11-22T10:00:00+01:00', 'minorsOnly' => true, 'maxAge' => 14]),
            'canicross' => $this->addActivity($eventId, 'Canicross', ['startsAt' => '2026-11-22T12:00:00+01:00']),
        ];
        $variants = [
            'trail5' => $this->addVariant($eventId, $activities['trail5'], 'Jednotlivec', 30000, 100),
            'trail10' => $this->addVariant($eventId, $activities['trail10'], 'Jednotlivec', 40000, 150),
            'relay' => $this->addVariant($eventId, $activities['relay'], 'Tým 4 os.', 120000, 20, 4),
            'kids' => $this->addVariant($eventId, $activities['kids'], 'Dítě', 10000, 80),
            'canicross' => $this->addVariant($eventId, $activities['canicross'], 'Jednotlivec', 35000, 40),
        ];

        self::assertSame(200, $this->owner->request('PUT', self::ORG.'/events/'.$eventId.'/payment-rules', ['methods' => ['cash'], 'dueDays' => 7, 'holdTtlHours' => 48])->status);
        $form = $this->owner->request('PUT', self::ORG.'/events/'.$eventId.'/form/draft', ['fields' => self::demoFormFields($activities, $variants)]);
        self::assertSame(200, $form->status, $form->body);

        return ['eventId' => $eventId, 'slug' => (string) $created['slug'], 'activities' => $activities, 'variants' => $variants];
    }

    /**
     * @param array<string, string> $activities
     * @param array<string, string> $variants
     *
     * @return list<array<string, mixed>>
     */
    protected static function demoFormFields(array $activities, array $variants): array
    {
        return [
            ['key' => 'first_name', 'type' => 'short_text', 'label' => 'Jméno', 'scope' => 'person', 'required' => true, 'semantic' => 'first_name'],
            ['key' => 'last_name', 'type' => 'short_text', 'label' => 'Příjmení', 'scope' => 'person', 'required' => true, 'semantic' => 'last_name'],
            ['key' => 'birth_date', 'type' => 'date', 'label' => 'Datum narození', 'scope' => 'person', 'required' => true, 'semantic' => 'birth_date'],
            ['key' => 'tshirt', 'type' => 'single_choice', 'label' => 'Velikost trička', 'scope' => 'person', 'constraints' => ['choices' => [['value' => 'S', 'label' => 'S'], ['value' => 'M', 'label' => 'M'], ['value' => 'L', 'label' => 'L']]]],
            ['key' => 'health_declaration', 'type' => 'legal_requirement', 'label' => 'Prohlášení o zdravotní způsobilosti', 'scope' => 'person', 'required' => true, 'activityIds' => [$activities['trail10']]],
            ['key' => 'team_name', 'type' => 'short_text', 'label' => 'Název týmu', 'scope' => 'group', 'required' => true, 'variantIds' => [$variants['relay']]],
            ['key' => 'dog_name', 'type' => 'short_text', 'label' => 'Jméno psa', 'scope' => 'person', 'required' => true, 'activityIds' => [$activities['canicross']]],
            ['key' => 'guardian_first_name', 'type' => 'short_text', 'label' => 'Jméno zákonného zástupce', 'scope' => 'person', 'required' => true, 'semantic' => 'guardian_first_name', 'activityIds' => [$activities['kids']]],
            ['key' => 'guardian_last_name', 'type' => 'short_text', 'label' => 'Příjmení zákonného zástupce', 'scope' => 'person', 'required' => true, 'semantic' => 'guardian_last_name', 'activityIds' => [$activities['kids']]],
            ['key' => 'guardian_email', 'type' => 'email', 'label' => 'E-mail zákonného zástupce', 'scope' => 'person', 'required' => true, 'semantic' => 'guardian_email', 'activityIds' => [$activities['kids']]],
            ['key' => 'orderer_first_name', 'type' => 'short_text', 'label' => 'Jméno objednávajícího', 'scope' => 'order', 'required' => true, 'semantic' => 'first_name'],
            ['key' => 'orderer_last_name', 'type' => 'short_text', 'label' => 'Příjmení objednávajícího', 'scope' => 'order', 'required' => true, 'semantic' => 'last_name'],
            ['key' => 'orderer_email', 'type' => 'email', 'label' => 'E-mail', 'scope' => 'order', 'required' => true, 'semantic' => 'email'],
            ['key' => 'orderer_phone', 'type' => 'phone', 'label' => 'Telefon', 'scope' => 'order', 'semantic' => 'phone'],
        ];
    }
}
