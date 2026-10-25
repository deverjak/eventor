<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Catalog;

use Eventor\Tests\Support\Catalog\CatalogHttpTestCase;
use Eventor\Tests\Support\Catalog\Json;

/**
 * The Catalog side of PUB-01..03: the public page of a published event without any session.
 */
final class PublicEventEndpointsTest extends CatalogHttpTestCase
{
    public function test_a_published_event_is_served_with_activities_prices_program_news_and_form(): void
    {
        $this->login(self::OWNER);
        $event = $this->createPublishableEvent('vikend');
        $this->request('POST', self::BASE.'/events/'.$event['eventId'].'/program', ['title' => 'Rozcvička', 'startsAt' => '2026-11-21T07:30:00+01:00']);
        $this->request('POST', self::BASE.'/events/'.$event['eventId'].'/program', ['title' => 'Porada týmu', 'startsAt' => '2026-11-21T07:00:00+01:00', 'visibility' => 'participants']);
        $this->request('POST', self::BASE.'/events/'.$event['eventId'].'/news', ['title' => 'Vítejte', 'body' => 'Těšíme se.', 'publish' => true]);
        $this->request('POST', self::BASE.'/events/'.$event['eventId'].'/news', ['title' => 'Koncept', 'body' => 'Ještě ne.']);
        $this->publish($event['eventId']);

        $this->client->getCookieJar()->clear();
        $page = $this->request('GET', '/api/public/orgs/klub/events/vikend');

        self::assertResponseIsSuccessful();
        self::assertIsArray($page);
        self::assertSame('Borský podzimní víkend 2026', $page['name']);
        self::assertSame('published', $page['publicationState']);
        self::assertSame('not_open', $page['registrationState']);
        self::assertSame('Europe/Prague', $page['timezone']);
        self::assertSame('Borský park', Json::at($page, 'venues.0.name'));
        self::assertSame(['cash'], $page['paymentMethods']);
        self::assertSame(['Trail 5 km', 'Týmová štafeta'], Json::column(Json::at($page, 'activities'), 'name'));
        $trail = Json::at($page, 'activities.0');
        self::assertSame(30000, Json::at($trail, 'variants.0.price.minor'));
        self::assertSame('Jednotlivec', Json::at($trail, 'variants.0.name'));
        self::assertSame(4, Json::at($page, 'activities.1.variants.0.teamSize'));
        self::assertSame(['Rozcvička'], Json::column(Json::at($page, 'program'), 'title'), 'only the public program is shown');
        self::assertSame(['Vítejte'], Json::column(Json::at($page, 'news'), 'title'), 'drafts are not shown');
        self::assertSame(1, Json::at($page, 'form.number'));
        self::assertContains('first_name', Json::column(Json::at($page, 'form.fields'), 'key'));
        self::assertArrayNotHasKey('capacityBucketId', Json::arr($trail), 'internal ids stay inside');
        self::assertArrayNotHasKey('version', $page);
    }

    public function test_the_page_shows_the_availability_of_registration(): void
    {
        $this->login(self::OWNER);
        $event = $this->createPublishableEvent('vikend');
        $this->publish($event['eventId']);
        $this->connection()->executeStatement("UPDATE registration.capacity_bucket SET held = 95 WHERE scope_type = 'variant' AND scope_id = ?", [$event['trailVariant']]);
        $this->connection()->executeStatement("UPDATE registration.capacity_bucket SET confirmed = 20 WHERE scope_type = 'variant' AND scope_id = ?", [$event['relayVariant']]);

        $page = $this->request('GET', '/api/public/orgs/klub/events/vikend');

        self::assertIsArray($page);
        self::assertTrue($page['availabilityShown']);
        self::assertSame(['status' => 'low', 'available' => 5], Json::at($page, 'activities.0.variants.0.availability'));
        self::assertSame(['status' => 'sold_out', 'available' => 0], Json::at($page, 'activities.1.variants.0.availability'));
        self::assertSame(['status' => 'unlimited', 'available' => null], $page['availability']);
    }

    public function test_the_availability_has_its_own_light_endpoint(): void
    {
        $this->login(self::OWNER);
        $event = $this->createPublishableEvent('vikend');
        $this->publish($event['eventId']);
        $this->connection()->executeStatement("UPDATE registration.capacity_bucket SET held = 97 WHERE scope_type = 'variant' AND scope_id = ?", [$event['trailVariant']]);

        $availability = $this->request('GET', '/api/public/orgs/klub/events/vikend/availability');

        self::assertResponseIsSuccessful();
        self::assertIsArray($availability);
        self::assertTrue($availability['shown']);
        self::assertSame($event['eventId'], $availability['eventId']);
        self::assertSame(['status' => 'unlimited', 'available' => null], $availability['event']);
        self::assertSame(['status' => 'low', 'available' => 3], Json::at($availability, 'variants.'.Json::str($event, 'trailVariant')));
        self::assertSame(['status' => 'available', 'available' => 20], Json::at($availability, 'variants.'.Json::str($event, 'relayVariant')));
        self::assertSame(100, Json::at($availability, 'activities.'.Json::str($event, 'trail').'.available'), 'every bucket is its own row; the activity bucket was not touched');

        $this->request('GET', '/api/public/orgs/klub/events/neexistuje/availability');
        $this->assertProblem(404, 'event_not_found');
        $this->request('GET', '/api/public/orgs/neexistuje/events/vikend/availability');
        $this->assertProblem(404, 'not_found');
    }

    public function test_the_form_has_its_own_endpoint(): void
    {
        $this->login(self::OWNER);
        $event = $this->createPublishableEvent('vikend');
        $this->publish($event['eventId']);

        $form = $this->request('GET', '/api/public/orgs/klub/events/vikend/form');

        self::assertResponseIsSuccessful();
        self::assertIsArray($form);
        self::assertSame(1, $form['number']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', Json::str($form, 'schemaSha256'));
        $email = Json::at(Json::column(Json::at($form, 'fields'), null, 'key'), 'orderer_email');
        self::assertSame('order', Json::at($email, 'scope'));
        self::assertSame('email', Json::at($email, 'jsonSchema.format'));
    }

    public function test_a_draft_does_not_exist_for_the_public(): void
    {
        $this->login(self::OWNER);
        $this->createPublishableEvent('koncept');

        $this->request('GET', '/api/public/orgs/klub/events/koncept');
        $this->assertProblem(404, 'event_not_found');
        $this->request('GET', '/api/public/orgs/klub/events/koncept/form');
        $this->assertProblem(404, 'event_not_found');
        $list = $this->request('GET', '/api/public/orgs/klub/events');
        self::assertIsArray($list);
        self::assertSame([], $list['items']);
    }

    public function test_an_unlisted_event_is_served_by_its_link_but_not_listed(): void
    {
        $this->login(self::OWNER);
        $listed = $this->createPublishableEvent('verejna');
        $this->publish($listed['eventId']);
        $hidden = $this->createPublishableEvent('skryta');
        $this->request('PUT', self::BASE.'/events/'.$hidden['eventId'], self::details(['name' => 'Soukromý víkend', 'visibility' => 'unlisted']));
        $this->publish($hidden['eventId']);

        $list = $this->request('GET', '/api/public/orgs/klub/events');
        self::assertResponseIsSuccessful();
        self::assertIsArray($list);
        self::assertSame(['verejna'], Json::column(Json::at($list, 'items'), 'slug'));

        $page = $this->request('GET', '/api/public/orgs/klub/events/skryta');
        self::assertResponseIsSuccessful();
        self::assertIsArray($page);
        self::assertSame('Soukromý víkend', $page['name']);
    }

    public function test_a_cancelled_event_is_still_served_with_its_state(): void
    {
        $this->login(self::OWNER);
        $event = $this->createPublishableEvent('zrusena');
        $this->publish($event['eventId']);
        $this->request('POST', self::BASE.'/events/'.$event['eventId'].'/cancel', ['reason' => 'Bouřka', 'confirmed' => true]);

        $page = $this->request('GET', '/api/public/orgs/klub/events/zrusena');

        self::assertResponseIsSuccessful();
        self::assertIsArray($page);
        self::assertSame('cancelled', $page['publicationState']);
        self::assertSame('closed', $page['registrationState']);
    }

    public function test_unknown_organizers_and_events_are_404(): void
    {
        $this->login(self::OWNER);
        $event = $this->createPublishableEvent('vikend');
        $this->publish($event['eventId']);
        $this->createOwner('petr@example.test', 'cizi-klub');

        $this->request('GET', '/api/public/orgs/neexistuje/events/vikend');
        $this->assertProblem(404, 'not_found');
        $this->request('GET', '/api/public/orgs/neexistuje/events');
        $this->assertProblem(404, 'not_found');
        $this->request('GET', '/api/public/orgs/klub/events/neexistuje');
        $this->assertProblem(404, 'event_not_found');
        // The event of one organizer is invisible under another organizer's address.
        $this->request('GET', '/api/public/orgs/cizi-klub/events/vikend');
        $this->assertProblem(404, 'event_not_found');
    }
}
