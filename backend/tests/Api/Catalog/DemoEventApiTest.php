<?php

declare(strict_types=1);

namespace Eventor\Tests\Api\Catalog;

use Eventor\Tests\Api\Support\ApiFixtures;

/**
 * The acceptance path of the Catalog API for the frontend (F1): the demo event "Borský podzimní víkend 2026" is built
 * through the admin API, its form is edited, the event is published and read back through the public endpoint.
 */
final class DemoEventApiTest extends CatalogApiTestCase
{
    public function test_the_demo_event_is_built_published_and_read_through_the_public_endpoint(): void
    {
        $demo = $this->buildDemoEvent();
        $eventId = $demo['eventId'];

        // Draft: nothing is public yet.
        $public = '/api/public/orgs/'.ApiFixtures::ALPHA['slug'].'/events/'.$demo['slug'];
        $this->assertProblem($this->anonymous()->get($public), 404, 'event_not_found');
        $draft = $this->ok($this->owner->get(self::ORG.'/events/'.$eventId));
        self::assertSame('draft', $draft['publicationState']);
        self::assertCount(5, $draft['activities']);

        // Publication: form version 1 and the buckets of the event, five activities and five variants.
        $published = $this->ok($this->owner->post(self::ORG.'/events/'.$eventId.'/publish'));
        self::assertSame(1, $published['formVersion']);
        self::assertIsString($published['formVersionId']);

        $page = $this->ok($this->anonymous()->get($public));
        self::assertSame('Borský podzimní víkend 2026', $page['name']);
        self::assertSame($demo['slug'], $page['slug']);
        self::assertSame('published', $page['publicationState']);
        self::assertSame('not_open', $page['registrationState']);
        self::assertSame(['cash'], $page['paymentMethods']);
        self::assertSame('Borský park', $page['venues'][0]['name']);
        self::assertSame(
            ['Trail 5 km', 'Trail 10 km', 'Týmová štafeta', 'Dětský závod', 'Canicross'],
            array_column($page['activities'], 'name'),
        );

        $byName = array_column($page['activities'], null, 'name');
        self::assertSame(30000, $byName['Trail 5 km']['variants'][0]['price']['minor']);
        self::assertSame('CZK', $byName['Trail 5 km']['variants'][0]['price']['currency']);
        self::assertSame(15, $byName['Trail 10 km']['minAge']);
        self::assertSame(4, $byName['Týmová štafeta']['variants'][0]['teamSize']);
        self::assertSame('team', $byName['Týmová štafeta']['variants'][0]['capacityUnit']);
        self::assertTrue($byName['Dětský závod']['minorsOnly']);
        self::assertSame(10000, $byName['Dětský závod']['variants'][0]['price']['minor']);

        // The public form schema is the published version 1.
        self::assertSame($published['formVersionId'], $page['form']['formVersionId']);
        $fields = array_column($page['form']['fields'], null, 'key');
        self::assertSame([$demo['activities']['canicross']], $fields['dog_name']['activityIds']);
        self::assertSame([$demo['variants']['relay']], $fields['team_name']['variantIds']);
        self::assertSame('guardian_email', $fields['guardian_email']['semantic']);
        self::assertSame('legal_requirement', $fields['health_declaration']['type']);

        // The free places have a light endpoint of their own (PUB-03).
        $availability = $this->ok($this->anonymous()->get($public.'/availability'));
        self::assertSame($demo['eventId'], $availability['eventId']);
        self::assertArrayHasKey($demo['variants']['trail5'], $availability['variants']);
        self::assertSame(['status' => 'available', 'available' => 100], $availability['variants'][$demo['variants']['trail5']]);
        $this->assertProblem($this->anonymous()->get($public.'x/availability'), 404, 'event_not_found');

        // The same form has its own endpoint.
        $form = $this->ok($this->anonymous()->get($public.'/form'));
        self::assertSame($page['form'], $form);

        // The admin sees the frozen version and no draft.
        $overview = $this->ok($this->owner->get(self::ORG.'/events/'.$eventId.'/form'));
        self::assertNull($overview['draft']);
        self::assertSame(1, $overview['published']['number']);

        // The public list shows the event.
        $list = $this->ok($this->anonymous()->get('/api/public/orgs/'.ApiFixtures::ALPHA['slug'].'/events'));
        self::assertContains($demo['slug'], array_column($list['items'], 'slug'));
    }

    public function test_opening_the_registration_is_visible_on_the_public_page(): void
    {
        $demo = $this->buildDemoEvent();
        $this->ok($this->owner->post(self::ORG.'/events/'.$demo['eventId'].'/publish'));

        $opened = $this->ok($this->owner->post(self::ORG.'/events/'.$demo['eventId'].'/registration/open', ['cascade' => true]));
        self::assertSame('open', $opened['registrationState']);

        $page = $this->ok($this->anonymous()->get('/api/public/orgs/'.ApiFixtures::ALPHA['slug'].'/events/'.$demo['slug']));
        self::assertSame('open', $page['registrationState']);
        self::assertSame(['open', 'open', 'open', 'open', 'open'], array_column($page['activities'], 'registrationState'));
        foreach ($page['activities'] as $activity) {
            self::assertArrayHasKey('availability', $activity);
        }
    }

    public function test_publishing_an_incomplete_event_is_a_422_with_the_broken_rules(): void
    {
        $created = $this->createEvent();
        $eventId = (string) $created['eventId'];
        $activity = $this->addActivity($eventId, 'Trail 5 km');
        $this->addVariant($eventId, $activity, 'Jednotlivec', 30000);
        $this->addActivity($eventId, 'Prázdná aktivita');

        $response = $this->owner->post(self::ORG.'/events/'.$eventId.'/publish');

        $problem = $this->assertProblem($response, 422, 'event_not_publishable');
        $codes = array_column($problem['violations'], 'code');
        self::assertContains('activity_without_variant', $codes);
        self::assertContains('payment_method_required', $codes);
        self::assertSame('draft', $this->ok($this->owner->get(self::ORG.'/events/'.$eventId))['publicationState']);
    }

    public function test_a_transfer_without_a_connected_bank_account_cannot_be_published(): void
    {
        $demo = $this->buildDemoEvent();
        $this->ok($this->owner->request('PUT', self::ORG.'/events/'.$demo['eventId'].'/payment-rules', ['methods' => ['bank_transfer', 'cash']]));

        $problem = $this->assertProblem($this->owner->post(self::ORG.'/events/'.$demo['eventId'].'/publish'), 422, 'event_not_publishable');

        self::assertSame(['transfer_requires_pairrot'], array_column($problem['violations'], 'code'));
    }

    public function test_an_invalid_form_schema_is_a_422_with_every_violation(): void
    {
        $created = $this->createEvent();

        $response = $this->owner->request('PUT', self::ORG.'/events/'.$created['eventId'].'/form/draft', ['fields' => [
            ['key' => 'ok_field', 'type' => 'short_text', 'label' => 'V pořádku', 'scope' => 'person'],
            ['key' => 'ok_field', 'type' => 'short_text', 'label' => 'Dvakrát', 'scope' => 'person'],
            ['key' => 'size', 'type' => 'single_choice', 'label' => 'Velikost', 'scope' => 'person'],
            ['key' => 'when', 'type' => 'date', 'label' => 'Kdy', 'scope' => 'nowhere'],
        ]]);

        $problem = $this->assertProblem($response, 422, 'form_schema_invalid');
        $byPath = [];
        foreach ($problem['violations'] as $violation) {
            $byPath[$violation['path']] = $violation['code'];
        }
        self::assertSame('duplicate_key', $byPath['fields[1].key']);
        self::assertSame('invalid_value', $byPath['fields[2].constraints.choices']);
        self::assertSame('invalid_value', $byPath['fields[3].scope']);
    }

    public function test_another_organizers_data_is_not_found(): void
    {
        $demo = $this->buildDemoEvent();
        $beta = $this->loginAs(ApiFixtures::BETA);

        // Beta's owner is no member of alpha: 404, like a missing organizer.
        $this->assertProblem($beta->get(self::ORG.'/events'), 404, 'not_found');
        $this->assertProblem($beta->post(self::ORG.'/events', ['details' => self::details()]), 404, 'not_found');
        $this->assertProblem($beta->get(self::ORG.'/events/'.$demo['eventId'].'/form'), 404, 'not_found');

        // And the event of alpha is not reachable under beta's own address.
        $betaOrg = '/api/admin/org/'.ApiFixtures::BETA['slug'];
        $this->assertProblem($beta->get($betaOrg.'/events/'.$demo['eventId']), 404, 'event_not_found');
        $this->assertProblem($beta->post($betaOrg.'/events/'.$demo['eventId'].'/publish'), 404, 'event_not_found');
        $this->assertProblem($beta->request('PUT', $betaOrg.'/events/'.$demo['eventId'].'/form/draft', ['fields' => []]), 404, 'event_not_found');

        // Publicly the event exists only under alpha's slug.
        $this->ok($this->owner->post(self::ORG.'/events/'.$demo['eventId'].'/publish'));
        $this->assertProblem($this->anonymous()->get('/api/public/orgs/'.ApiFixtures::BETA['slug'].'/events/'.$demo['slug']), 404, 'event_not_found');
        $this->assertProblem($this->anonymous()->get('/api/public/orgs/no-such-org/events/'.$demo['slug']), 404, 'not_found');
    }

    public function test_the_admin_zone_needs_a_session(): void
    {
        $anonymous = $this->anonymous();

        $this->assertProblem($anonymous->get(self::ORG.'/events'), 401, 'unauthorized');
        $this->assertProblem($anonymous->post(self::ORG.'/events', ['details' => self::details()]), 401, 'unauthorized');
        $this->assertProblem($anonymous->post(self::ORG.'/events/0197b4a0-0000-7000-8000-000000000000/publish'), 401, 'unauthorized');
    }
}
