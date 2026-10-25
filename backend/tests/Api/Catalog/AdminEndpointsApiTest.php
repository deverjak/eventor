<?php

declare(strict_types=1);

namespace Eventor\Tests\Api\Catalog;

/**
 * One happy path and one error path for every admin endpoint of Catalog that the demo flow does not touch.
 */
final class AdminEndpointsApiTest extends CatalogApiTestCase
{
    public function test_event_list_detail_update_and_payment_rules(): void
    {
        $slug = self::uniqueSlug();
        $created = $this->createEvent($slug, 'simple', ['name' => 'Jednoduchá akce']);
        $eventId = (string) $created['eventId'];

        $list = $this->ok($this->owner->get(self::ORG.'/events'));
        self::assertContains($eventId, array_column($list['items'], 'id'));

        $event = $this->ok($this->owner->get(self::ORG.'/events/'.$eventId));
        self::assertSame($slug, $event['slug']);
        self::assertCount(1, $event['activities']);
        self::assertTrue($event['activities'][0]['implicit']);
        self::assertSame(1, $event['version']);

        $updated = $this->ok($this->owner->request('PUT', self::ORG.'/events/'.$eventId, self::details(['name' => 'Přejmenovaná akce', 'visibility' => 'unlisted']), ['If-Match' => '1']));
        self::assertSame('Přejmenovaná akce', $updated['name']);
        self::assertSame(2, $updated['version']);
        $this->assertProblem($this->owner->request('PUT', self::ORG.'/events/'.$eventId, self::details(), ['If-Match' => '1']), 409, 'version_conflict');
        $this->assertProblem($this->owner->request('PUT', self::ORG.'/events/'.$eventId, self::details(['name' => ''])), 422, 'validation_failed');

        $rules = $this->ok($this->owner->request('PUT', self::ORG.'/events/'.$eventId.'/payment-rules', ['methods' => ['cash'], 'dueDays' => 10, 'holdTtlHours' => 24]));
        self::assertSame(['cash'], $rules['paymentRules']['methods']);
        $this->assertProblem($this->owner->request('PUT', self::ORG.'/events/'.$eventId.'/payment-rules', ['methods' => ['barter']]), 422, 'validation_failed');

        $this->assertProblem($this->owner->get(self::ORG.'/events/0197b4a0-0000-7000-8000-000000000000'), 404, 'event_not_found');
        $this->assertProblem($this->owner->post(self::ORG.'/events', ['details' => self::details(), 'slug' => $slug]), 409, 'event_slug_taken');
        $this->assertProblem($this->owner->post(self::ORG.'/events', ['details' => self::details(['endsAt' => '2020-01-01T00:00:00+00:00'])]), 422, 'invalid_catalog_data');
    }

    public function test_activities_variants_and_capacity(): void
    {
        $created = $this->createEvent();
        $eventId = (string) $created['eventId'];
        $base = self::ORG.'/events/'.$eventId;
        $activityId = $this->addActivity($eventId, 'Canicross', ['startsAt' => '2026-11-22T10:00:00+01:00']);
        $variantId = $this->addVariant($eventId, $activityId, 'Jednotlivec', 35000, 40);

        $updatedActivity = $this->ok($this->owner->request('PUT', $base.'/activities/'.$activityId, ['name' => 'Canicross 3 km', 'minAge' => 16]));
        self::assertSame('Canicross 3 km', $updatedActivity['activities'][0]['name']);
        $updatedVariant = $this->ok($this->owner->request('PUT', $base.'/variants/'.$variantId, ['name' => 'Jednotlivec', 'price' => ['minor' => 36000, 'currency' => 'CZK']]));
        self::assertSame(36000, $updatedVariant['activities'][0]['variants'][0]['price']['minor']);

        $capacity = $this->ok($this->owner->post($base.'/capacity', ['scope' => 'variant', 'scopeId' => $variantId, 'limit' => 60]));
        self::assertFalse($capacity['belowOccupancy']);
        self::assertSame(60, $capacity['event']['activities'][0]['variants'][0]['capacityLimit']);

        $this->assertProblem($this->owner->post($base.'/activities', ['name' => '']), 422, 'validation_failed');
        $this->assertProblem($this->owner->post($base.'/activities/0197b4a0-0000-7000-8000-000000000000/variants', ['name' => 'x', 'price' => ['minor' => 1, 'currency' => 'CZK']]), 404, 'activity_not_found');
        $this->assertProblem($this->owner->request('PUT', $base.'/variants/0197b4a0-0000-7000-8000-000000000000', ['name' => 'x', 'price' => ['minor' => 1, 'currency' => 'CZK']]), 404, 'variant_not_found');
        $this->assertProblem($this->owner->post($base.'/capacity', ['scope' => 'galaxy']), 422, 'validation_failed');

        $this->ok($this->owner->request('DELETE', $base.'/variants/'.$variantId));
        $afterDelete = $this->ok($this->owner->request('DELETE', $base.'/activities/'.$activityId));
        self::assertSame([], $afterDelete['activities']);
        $this->assertProblem($this->owner->request('DELETE', $base.'/activities/'.$activityId), 404, 'activity_not_found');
    }

    public function test_a_published_event_locks_its_structure_but_accepts_capacity_changes_with_a_reason(): void
    {
        $demo = $this->buildDemoEvent();
        $base = self::ORG.'/events/'.$demo['eventId'];
        $this->ok($this->owner->post($base.'/publish'));

        $this->assertProblem($this->owner->request('DELETE', $base.'/activities/'.$demo['activities']['trail5']), 409, 'event_not_draft');
        $this->assertProblem($this->owner->post($base.'/capacity', ['scope' => 'variant', 'scopeId' => $demo['variants']['trail5'], 'limit' => 120]), 422, 'invalid_catalog_data');

        $capacity = $this->ok($this->owner->post($base.'/capacity', ['scope' => 'variant', 'scopeId' => $demo['variants']['trail5'], 'limit' => 120, 'reason' => 'Větší louka']));
        self::assertFalse($capacity['belowOccupancy']);
        self::assertSame([], $capacity['warnings']);

        $this->assertProblem($this->owner->post($base.'/publish'), 409, 'transition_not_allowed');
    }

    public function test_the_registration_window_follows_its_state_machine(): void
    {
        $demo = $this->buildDemoEvent();
        $base = self::ORG.'/events/'.$demo['eventId'];
        $trail = $demo['activities']['trail5'];

        $this->assertProblem($this->owner->post($base.'/registration/open'), 409, 'transition_not_allowed');
        $this->ok($this->owner->post($base.'/publish'));
        $this->assertProblem($this->owner->post($base.'/activities/'.$trail.'/registration/open'), 409, 'transition_not_allowed');

        self::assertSame('open', $this->ok($this->owner->post($base.'/registration/open'))['registrationState']);
        $this->ok($this->owner->post($base.'/activities/'.$trail.'/registration/open'));
        $this->ok($this->owner->post($base.'/activities/'.$trail.'/registration/pause', ['reason' => 'Rozbité značení']));
        $this->assertProblem($this->owner->post($base.'/activities/'.$trail.'/registration/pause'), 409, 'transition_not_allowed');
        $this->ok($this->owner->post($base.'/activities/'.$trail.'/registration/resume'));
        $closed = $this->ok($this->owner->post($base.'/registration/close', ['cascade' => true]));
        self::assertSame('closed', $closed['registrationState']);
        self::assertSame(array_fill(0, 5, 'closed'), array_column($closed['activities'], 'registrationState'));
    }

    public function test_an_event_is_cancelled_by_the_owner_with_a_reason_and_confirmation(): void
    {
        $demo = $this->buildDemoEvent();
        $base = self::ORG.'/events/'.$demo['eventId'];
        $this->ok($this->owner->post($base.'/publish'));
        $this->ok($this->owner->post($base.'/registration/open', ['cascade' => true]));

        $this->assertProblem($this->owner->post($base.'/cancel', ['reason' => 'Bouřka']), 422, 'validation_failed');
        $cancelled = $this->ok($this->owner->post($base.'/cancel', ['reason' => 'Bouřka', 'confirmed' => true]));

        self::assertSame('cancelled', $cancelled['publicationState']);
        self::assertSame('Bouřka', $cancelled['cancellationReason']);
        self::assertSame('closed', $cancelled['registrationState']);
        $this->assertProblem($this->owner->post($base.'/cancel', ['reason' => 'Znovu', 'confirmed' => true]), 409, 'transition_not_allowed');
        $archived = $this->ok($this->owner->post($base.'/archive'));
        self::assertSame('archived', $archived['publicationState']);
        $this->assertProblem($this->owner->post($base.'/archive'), 409, 'transition_not_allowed');
    }

    public function test_an_event_that_is_over_can_be_completed_and_archived(): void
    {
        $slug = self::uniqueSlug('minuly');
        $created = $this->createEvent($slug, 'multi', ['startsAt' => '2020-05-02T08:00:00+02:00', 'endsAt' => '2020-05-03T16:00:00+02:00']);
        $eventId = (string) $created['eventId'];
        $base = self::ORG.'/events/'.$eventId;
        $activity = $this->addActivity($eventId, 'Závod');
        $this->addVariant($eventId, $activity, 'Zdarma', 0);
        $this->ok($this->owner->post($base.'/publish'));

        $completed = $this->ok($this->owner->post($base.'/complete'));
        self::assertSame('completed', $completed['publicationState']);
        self::assertSame('closed', $completed['registrationState']);
        $this->assertProblem($this->owner->post($base.'/complete'), 409, 'transition_not_allowed');

        self::assertSame('archived', $this->ok($this->owner->post($base.'/archive'))['publicationState']);
    }

    public function test_an_event_that_is_not_over_cannot_be_completed(): void
    {
        $demo = $this->buildDemoEvent();
        $base = self::ORG.'/events/'.$demo['eventId'];
        $this->ok($this->owner->post($base.'/publish'));

        $problem = $this->assertProblem($this->owner->post($base.'/complete'), 409, 'transition_not_allowed');

        self::assertStringContainsString('has not ended', (string) $problem['detail']);
    }

    public function test_form_versions_s17(): void
    {
        $demo = $this->buildDemoEvent();
        $base = self::ORG.'/events/'.$demo['eventId'].'/form';
        $this->assertProblem($this->owner->post($base.'/publish'), 422, 'invalid_catalog_data');
        $this->ok($this->owner->post(self::ORG.'/events/'.$demo['eventId'].'/publish'));
        $this->assertProblem($this->owner->post($base.'/publish'), 422, 'invalid_catalog_data');

        $fields = self::demoFormFields($demo['activities'], $demo['variants']);
        $fields[] = ['key' => 'emergency_phone', 'type' => 'phone', 'label' => 'Telefon pro nouzi', 'scope' => 'person', 'required' => true];
        $draft = $this->ok($this->owner->request('PUT', $base.'/draft', ['fields' => $fields]));
        self::assertSame(2, $draft['draft']['number']);
        self::assertSame(1, $draft['published']['number'], 'the published version is untouched');

        self::assertSame(2, $this->ok($this->owner->post($base.'/publish'))['number']);
        $overview = $this->ok($this->owner->get($base));
        self::assertSame([2, 1], array_column($overview['versions'], 'number'));
        self::assertCount(14, $this->ok($this->owner->get($base.'/versions/1'))['fields']);
        self::assertCount(15, $this->ok($this->owner->get($base.'/versions/2'))['fields']);
        $this->assertProblem($this->owner->get($base.'/versions/3'), 404, 'form_version_not_found');

        $public = $this->ok($this->anonymous()->get('/api/public/orgs/api-alpha/events/'.$demo['slug'].'/form'));
        self::assertSame(2, $public['number'], 'new registrations use the newest version');
    }

    public function test_field_types_of_the_editor(): void
    {
        $catalog = $this->ok($this->owner->get(self::ORG.'/form-field-types'));

        self::assertCount(11, $catalog['types']);
        self::assertContains('legal_requirement', array_column($catalog['types'], 'name'));
        $this->assertProblem($this->anonymous()->get(self::ORG.'/form-field-types'), 401, 'unauthorized');
    }

    public function test_program_and_news(): void
    {
        $demo = $this->buildDemoEvent();
        $eventId = $demo['eventId'];
        $program = self::ORG.'/events/'.$eventId.'/program';
        $news = self::ORG.'/events/'.$eventId.'/news';

        $added = $this->ok($this->owner->post($program, ['title' => 'Start Trailu 5 km', 'startsAt' => '2026-11-21T10:00:00+01:00', 'activityId' => $demo['activities']['trail5'], 'location' => 'Louka']), 201);
        $this->ok($this->owner->post($program, ['title' => 'Porada pořadatelů', 'startsAt' => '2026-11-21T07:00:00+01:00', 'visibility' => 'participants']), 201);
        $listed = $this->ok($this->owner->get($program));
        self::assertSame(['Porada pořadatelů', 'Start Trailu 5 km'], array_column($listed['items'], 'title'));
        $changed = $this->ok($this->owner->request('PUT', $program.'/'.$added['programItemId'], ['title' => 'Start Trailu 5 km (posunut)', 'startsAt' => '2026-11-21T10:30:00+01:00', 'important' => true]));
        self::assertSame('Start Trailu 5 km (posunut)', $changed['items'][1]['title']);
        $this->assertProblem($this->owner->post($program, ['title' => 'Pozpátku', 'startsAt' => '2026-11-21T10:00:00+01:00', 'endsAt' => '2026-11-21T09:00:00+01:00']), 422, 'invalid_catalog_data');
        $this->assertProblem($this->owner->request('PUT', $program.'/0197b4a0-0000-7000-8000-000000000000', ['title' => 'x', 'startsAt' => '2026-11-21T10:00:00+01:00']), 404, 'program_item_not_found');
        $this->ok($this->owner->request('DELETE', $program.'/'.$added['programItemId']));

        $post = $this->ok($this->owner->post($news, ['title' => 'Registrace otevřena', 'body' => 'Přihlaste se.', 'priority' => 2]), 201);
        self::assertFalse($post['published']);
        $this->ok($this->owner->request('PUT', $news.'/'.$post['newsPostId'], ['title' => 'Registrace je otevřena', 'body' => 'Přihlaste se na webu.']));
        $this->ok($this->owner->post($news.'/'.$post['newsPostId'].'/publish'));
        $this->assertProblem($this->owner->post($news.'/'.$post['newsPostId'].'/publish'), 409, 'news_post_already_published');
        $this->assertProblem($this->owner->post($news, ['title' => '', 'body' => '']), 422, 'validation_failed');
        $items = $this->ok($this->owner->get($news))['items'];
        self::assertTrue($items[0]['published']);

        $this->ok($this->owner->post(self::ORG.'/events/'.$eventId.'/publish'));
        $public = $this->ok($this->anonymous()->get('/api/public/orgs/api-alpha/events/'.$demo['slug']));
        self::assertSame(['Registrace je otevřena'], array_column($public['news'], 'title'));
        self::assertNotContains('Porada pořadatelů', array_column($public['program'], 'title'), 'a participants-only item is not public');
    }
}
