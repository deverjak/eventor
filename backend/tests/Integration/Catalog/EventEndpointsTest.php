<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Catalog;

use Eventor\Identity\Domain\Membership\Role;
use Eventor\Tests\Support\Catalog\CatalogHttpTestCase;
use Eventor\Tests\Support\Catalog\Json;

/**
 * EVT-01, EVT-02, EVT-10, EVT-11 over HTTP: happy paths and the 401 / 403 / 404 / 409 / 422 guards.
 */
final class EventEndpointsTest extends CatalogHttpTestCase
{
    public function test_simple_mode_creates_a_draft_with_the_implicit_activity_and_variant(): void
    {
        $this->login(self::OWNER);

        $created = $this->request('POST', self::BASE.'/events', [
            'details' => self::details(),
            'mode' => 'simple',
            'price' => ['minor' => 30000, 'currency' => 'CZK'],
            'capacityLimit' => 100,
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertIsArray($created);
        self::assertSame('borsky-podzimni-vikend-2026', $created['slug']);
        $event = $this->request('GET', self::BASE.'/events/'.Json::str($created, 'eventId'));
        self::assertIsArray($event);
        self::assertSame('draft', $event['publicationState']);
        self::assertSame('not_open', $event['registrationState']);
        self::assertSame('public', $event['visibility']);
        self::assertSame(1, $event['version']);
        self::assertCount(1, Json::arr($event, 'activities'));
        self::assertTrue(Json::at($event, 'activities.0.implicit'));
        self::assertSame('Borský podzimní víkend 2026', Json::at($event, 'activities.0.name'));
        $variant = Json::at($event, 'activities.0.variants.0');
        self::assertSame(30000, Json::at($variant, 'price.minor'));
        self::assertSame(100, Json::at($variant, 'capacityLimit'));
        self::assertSame('seat', Json::at($variant, 'capacityUnit'));
        self::assertSame('Borský park', Json::at($event, 'venues.0.name'));
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM audit.audit_entry WHERE action = 'event.created' AND entity_id = ?", [$created['eventId']]));
        self::assertSame(1, $this->dbInt('SELECT count(*) FROM catalog.form_version WHERE event_id = ? AND published_at IS NULL AND number = 1', [$created['eventId']]));
    }

    public function test_multi_mode_creates_an_empty_structure_and_slugs_are_made_unique(): void
    {
        $this->login(self::OWNER);

        $first = $this->createEvent('multi');
        $second = $this->createEvent('multi');

        self::assertSame('borsky-podzimni-vikend-2026', $first['slug']);
        self::assertSame('borsky-podzimni-vikend-2026-2', $second['slug']);
        self::assertNull($first['activityId']);
        $event = $this->request('GET', self::BASE.'/events/'.Json::str($first, 'eventId'));
        self::assertIsArray($event);
        self::assertSame([], $event['activities']);
    }

    public function test_an_explicit_slug_that_is_taken_is_a_409(): void
    {
        $this->login(self::OWNER);
        $this->createEvent(slug: 'vikend');

        $this->request('POST', self::BASE.'/events', ['details' => self::details(), 'slug' => 'vikend']);

        $this->assertProblem(409, 'event_slug_taken');
    }

    public function test_the_event_list_shows_events_newest_first(): void
    {
        $this->login(self::OWNER);
        $this->createEvent(detailsOverrides: ['name' => 'Jarní', 'startsAt' => '2027-04-01T08:00:00+02:00', 'endsAt' => '2027-04-01T16:00:00+02:00']);
        $this->createEvent(detailsOverrides: ['name' => 'Podzimní']);

        $list = $this->request('GET', self::BASE.'/events');

        self::assertResponseIsSuccessful();
        self::assertIsArray($list);
        self::assertSame(['Jarní', 'Podzimní'], Json::column(Json::at($list, 'items'), 'name'));
    }

    public function test_validation_errors_are_422_with_violations(): void
    {
        $this->login(self::OWNER);

        $this->request('POST', self::BASE.'/events', ['details' => self::details(['name' => '', 'contactEmail' => 'not-mail', 'visibility' => 'secret'])]);
        $problem = $this->assertProblem(422, 'validation_failed');
        $paths = Json::column(Json::at($problem, 'violations'), 'path');
        self::assertContains('details.name', $paths);
        self::assertContains('details.contactEmail', $paths);
        self::assertContains('details.visibility', $paths);

        $this->request('POST', self::BASE.'/events', ['details' => self::details(['endsAt' => '2026-11-20T08:00:00+01:00'])]);
        $this->assertProblem(422, 'invalid_catalog_data');

        $this->request('POST', self::BASE.'/events', ['details' => self::details(['timezone' => 'Mars/Olympus'])]);
        $this->assertProblem(422, 'invalid_catalog_data');
    }

    public function test_updating_details_uses_the_optimistic_lock(): void
    {
        $this->login(self::OWNER);
        $created = $this->createEvent();
        $uri = self::BASE.'/events/'.Json::str($created, 'eventId');

        $updated = $this->request('PUT', $uri, self::details(['name' => 'Nový název', 'visibility' => 'unlisted']), ['If-Match' => '1']);
        self::assertResponseIsSuccessful();
        self::assertIsArray($updated);
        self::assertSame('Nový název', $updated['name']);
        self::assertSame('unlisted', $updated['visibility']);
        self::assertSame(2, $updated['version']);
        self::assertSame('borsky-podzimni-vikend-2026', $updated['slug'], 'the address never changes with the name');

        $this->request('PUT', $uri, self::details(['name' => 'Stará verze']), ['If-Match' => '1']);
        $this->assertProblem(409, 'version_conflict');

        $this->request('PUT', $uri, self::details(['name' => 'Bez zámku']));
        self::assertResponseIsSuccessful();
        self::assertSame(2, $this->dbInt("SELECT count(*) FROM audit.audit_entry WHERE action = 'event.details_changed' AND entity_id = ?", [$created['eventId']]));
    }

    public function test_payment_rules_are_saved_and_validated(): void
    {
        $this->login(self::OWNER);
        $created = $this->createEvent();
        $uri = self::BASE.'/events/'.Json::str($created, 'eventId').'/payment-rules';

        $event = $this->request('PUT', $uri, ['methods' => ['bank_transfer', 'cash'], 'dueDays' => 14, 'holdTtlHours' => 72, 'autoRestoreOnLatePayment' => true, 'selfServiceCancellationDeadline' => '2026-11-14T23:59:00+01:00']);
        self::assertResponseIsSuccessful();
        self::assertIsArray($event);
        self::assertSame(['bank_transfer', 'cash'], Json::at($event, 'paymentRules.methods'));
        self::assertSame(14, Json::at($event, 'paymentRules.dueDays'));
        self::assertTrue(Json::at($event, 'paymentRules.autoRestoreOnLatePayment'));

        $this->request('PUT', $uri, ['methods' => ['free'], 'dueDays' => 0]);
        $problem = $this->assertProblem(422, 'validation_failed');
        self::assertContains('dueDays', Json::column(Json::at($problem, 'violations'), 'path'));
    }

    public function test_anonymous_access_is_401(): void
    {
        $this->request('POST', self::BASE.'/events', ['details' => self::details()]);
        $this->assertProblem(401, 'unauthorized');

        $this->request('GET', self::BASE.'/events');
        $this->assertProblem(401, 'unauthorized');
    }

    public function test_a_check_in_operator_may_read_but_not_write(): void
    {
        $this->addMember('obsluha@example.test', Role::CheckIn);
        $this->login(self::OWNER);
        $created = $this->createEvent();
        $this->login('obsluha@example.test');

        $this->request('GET', self::BASE.'/events');
        self::assertResponseIsSuccessful();
        $this->request('GET', self::BASE.'/events/'.Json::str($created, 'eventId'));
        self::assertResponseIsSuccessful();

        $this->request('POST', self::BASE.'/events', ['details' => self::details()]);
        $this->assertProblem(403, 'forbidden');
        $this->request('PUT', self::BASE.'/events/'.Json::str($created, 'eventId'), self::details());
        $this->assertProblem(403, 'forbidden');
        $this->request('PUT', self::BASE.'/events/'.Json::str($created, 'eventId').'/payment-rules', ['methods' => ['cash']]);
        $this->assertProblem(403, 'forbidden');
    }

    public function test_another_organizer_is_404_and_looks_like_a_missing_one(): void
    {
        $this->createOwner('petr@example.test', 'cizi-klub');
        $this->login('petr@example.test');
        $foreign = $this->createEventOf('cizi-klub');

        $this->login(self::OWNER);
        $this->request('GET', '/api/admin/org/cizi-klub/events');
        $this->assertProblem(404, 'not_found');
        $this->request('POST', '/api/admin/org/cizi-klub/events', ['details' => self::details()]);
        $this->assertProblem(404, 'not_found');

        // The event of the other organizer under my own organizer: not found either.
        $this->request('GET', self::BASE.'/events/'.$foreign);
        $this->assertProblem(404, 'event_not_found');
        $this->request('PUT', self::BASE.'/events/'.$foreign, self::details());
        $this->assertProblem(404, 'event_not_found');
        $this->request('POST', self::BASE.'/events/'.$foreign.'/publish');
        $this->assertProblem(404, 'event_not_found');
    }

    public function test_an_unknown_or_malformed_event_id_is_404(): void
    {
        $this->login(self::OWNER);

        $this->request('GET', self::BASE.'/events/0197b4a0-0000-7000-8000-000000000000');
        $this->assertProblem(404, 'event_not_found');
        $this->request('GET', self::BASE.'/events/not-a-uuid');
        $this->assertProblem(404, 'not_found');
    }

    private function createEventOf(string $slug): string
    {
        $created = $this->request('POST', '/api/admin/org/'.$slug.'/events', ['details' => self::details()]);
        self::assertResponseStatusCodeSame(201);
        self::assertIsArray($created);

        return Json::str($created, 'eventId');
    }
}
