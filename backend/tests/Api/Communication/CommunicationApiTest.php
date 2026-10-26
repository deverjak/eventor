<?php

declare(strict_types=1);

namespace Eventor\Tests\Api\Communication;

use Eventor\Tests\Api\Support\ApiClient;
use Eventor\Tests\Api\Support\ApiFixtures;
use Eventor\Tests\Api\Support\ApiTestCase;
use Eventor\Tests\Support\Catalog\Json;

/**
 * Black-box tests of the Communication API (COM-01 to COM-08, PRV-07, PAY-09): real HTTP against the running stack. The
 * event comes from the Catalog API, every name is unique per test. Registration is built in parallel, so the audiences
 * here are empty; the deliveries themselves are covered by the scenarios.
 */
final class CommunicationApiTest extends ApiTestCase
{
    private const string ORG = '/api/admin/org/'.ApiFixtures::ALPHA['slug'];
    private const string UNKNOWN = '0197a000-0000-7000-8000-00000000dead';

    private ApiClient $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->loginAs(ApiFixtures::ALPHA);
    }

    public function test_template_crud_variables_and_preview(): void
    {
        $key = 'payment_reminder';
        $locale = 'en';

        $builtIn = $this->owner->get(self::ORG.'/message-templates?key='.$key.'&locale='.$locale);
        self::assertSame(200, $builtIn->status, $builtIn->body);
        self::assertSame('built_in', Json::at($builtIn->json(), 'items.0.source'));

        $variables = $this->owner->get(self::ORG.'/message-templates/variables');
        self::assertSame(200, $variables->status);
        self::assertArrayHasKey('selfServiceUrl', Json::arr($variables->json(), 'variables'));

        $preview = $this->owner->post(self::ORG.'/message-templates/preview', ['subject' => 'Ahoj {{ recipient.name }}', 'body' => 'Zaplaťte {{ order.outstanding }} na {{ payment.accountNumber }}.']);
        self::assertSame(200, $preview->status, $preview->body);
        self::assertSame('Ahoj Jana Nováková', Json::str($preview->json(), 'subject'));
        self::assertStringContainsString('123456789/0100', Json::str($preview->json(), 'body'));

        $created = $this->owner->post(self::ORG.'/message-templates', ['key' => $key, 'locale' => $locale, 'subject' => 'Pay {{ order.number }}', 'body' => 'Pay {{ order.outstanding }}']);
        if (409 === $created->status) {
            // a rerun against the same database: start from a clean slate
            $existing = Json::str($this->owner->get(self::ORG.'/message-templates?key='.$key.'&locale='.$locale)->json(), 'items.0.id');
            self::assertSame(204, $this->owner->request('DELETE', self::ORG.'/message-templates/'.$existing)->status);
            $created = $this->owner->post(self::ORG.'/message-templates', ['key' => $key, 'locale' => $locale, 'subject' => 'Pay {{ order.number }}', 'body' => 'Pay {{ order.outstanding }}']);
        }
        self::assertSame(201, $created->status, $created->body);
        $id = Json::str($created->json(), 'templateId');

        $this->assertProblem($this->owner->post(self::ORG.'/message-templates', ['key' => $key, 'locale' => $locale, 'subject' => 'x', 'body' => 'y']), 409, 'template_exists');

        $updated = $this->owner->request('PUT', self::ORG.'/message-templates/'.$id, ['subject' => 'Reminder {{ order.number }}', 'body' => 'Still {{ order.outstanding }}']);
        self::assertSame(200, $updated->status, $updated->body);
        self::assertSame('Reminder {{ order.number }}', Json::str($updated->json(), 'subject'));
        self::assertSame('custom', Json::str($this->owner->get(self::ORG.'/message-templates?key='.$key.'&locale='.$locale)->json(), 'items.0.source'));
        self::assertSame(200, $this->owner->get(self::ORG.'/message-templates/'.$id)->status);

        self::assertSame(204, $this->owner->request('DELETE', self::ORG.'/message-templates/'.$id)->status);
        $this->assertProblem($this->owner->get(self::ORG.'/message-templates/'.$id), 404, 'message_template_not_found');
    }

    public function test_template_errors(): void
    {
        $this->assertProblem($this->owner->post(self::ORG.'/message-templates', ['key' => 'order_received', 'locale' => 'cs', 'subject' => 'x', 'body' => '{{ nonsense.variable }}']), 422, 'template_invalid');
        $this->assertProblem($this->owner->post(self::ORG.'/message-templates', ['key' => 'order_received', 'locale' => 'cs', 'subject' => 'x', 'body' => '{% include "evil" %}']), 422, 'template_invalid');
        $this->assertProblem($this->owner->post(self::ORG.'/message-templates', ['key' => 'galaxy', 'locale' => 'cs', 'subject' => 'x', 'body' => 'y']), 422, 'validation_failed');
        $this->assertProblem($this->owner->post(self::ORG.'/message-templates', ['key' => 'order_received', 'locale' => 'cs', 'subject' => '', 'body' => 'y']), 422, 'validation_failed');
        $this->assertProblem($this->owner->post(self::ORG.'/message-templates/preview', ['subject' => 'x']), 422, 'template_invalid');
        $this->assertProblem($this->owner->request('PUT', self::ORG.'/message-templates/'.self::UNKNOWN, ['subject' => 'x', 'body' => 'y']), 404, 'message_template_not_found');
        $this->assertProblem($this->owner->request('DELETE', self::ORG.'/message-templates/'.self::UNKNOWN), 404, 'message_template_not_found');
    }

    public function test_campaign_create_preview_target_schedule_send_and_history(): void
    {
        $event = $this->createEvent();
        $audience = ['activityIds' => [], 'participationStates' => ['confirmed'], 'financialStates' => ['paid'], 'checkedIn' => false];
        $name = 'Novinky '.bin2hex(random_bytes(3));

        $preview = $this->owner->post(self::ORG.'/campaigns/preview', ['eventId' => $event, 'name' => $name, 'subject' => 'Ahoj {{ recipient.name }}', 'body' => 'Zpráva k {{ event.name }}', 'audience' => $audience]);
        self::assertSame(200, $preview->status, $preview->body);
        self::assertSame(0, Json::int($preview->json(), 'recipientCount'));
        self::assertSame('Ahoj Jana Nováková', Json::str($preview->json(), 'subject'));

        $created = $this->owner->post(self::ORG.'/campaigns', ['eventId' => $event, 'name' => $name, 'purpose' => 'marketing', 'templateKey' => 'custom', 'audience' => $audience]);
        self::assertSame(201, $created->status, $created->body);
        $id = Json::str($created->json(), 'campaignId');

        $detail = $this->owner->get(self::ORG.'/campaigns/'.$id);
        self::assertSame(200, $detail->status, $detail->body);
        self::assertSame('draft', Json::str($detail->json(), 'state'));
        self::assertSame('marketing', Json::str($detail->json(), 'purpose'));
        self::assertSame(['paid'], Json::arr($detail->json(), 'audience.financialStates'));
        self::assertContains($id, Json::column(Json::at($this->owner->get(self::ORG.'/campaigns?eventId='.$event)->json(), 'items'), 'id'));

        $edited = $this->owner->request('PUT', self::ORG.'/campaigns/'.$id, ['name' => $name.' 2', 'purpose' => 'organizational', 'subject' => 'Nové', 'body' => 'Text', 'audience' => ['activityIds' => []]]);
        self::assertSame(200, $edited->status, $edited->body);
        self::assertSame('organizational', Json::str($edited->json(), 'purpose'));

        $this->assertProblem($this->owner->post(self::ORG.'/campaigns/'.$id.'/schedule', ['at' => '2020-01-01T10:00:00+00:00']), 422, 'invalid_campaign');
        $scheduled = $this->owner->post(self::ORG.'/campaigns/'.$id.'/schedule', ['at' => gmdate('c', time() + 86400)]);
        self::assertSame(200, $scheduled->status, $scheduled->body);
        self::assertSame('scheduled', Json::str($scheduled->json(), 'state'));
        $this->assertProblem($this->owner->request('PUT', self::ORG.'/campaigns/'.$id, ['name' => 'x', 'subject' => 's', 'body' => 'b']), 409, 'campaign_state_invalid');

        $sent = $this->owner->post(self::ORG.'/campaigns/'.$id.'/send');
        self::assertSame(200, $sent->status, $sent->body);
        self::assertSame(0, Json::int($sent->json(), 'recipients'));
        self::assertSame('sent', Json::str($this->owner->get(self::ORG.'/campaigns/'.$id)->json(), 'state'), 'a campaign without recipients is sent at once');
        $this->assertProblem($this->owner->post(self::ORG.'/campaigns/'.$id.'/send'), 409, 'campaign_state_invalid');
        $this->assertProblem($this->owner->post(self::ORG.'/campaigns/'.$id.'/cancel'), 409, 'campaign_state_invalid');

        $history = $this->owner->get(self::ORG.'/campaigns/'.$id.'/deliveries');
        self::assertSame(200, $history->status, $history->body);
        self::assertSame(0, Json::int($history->json(), 'total'));
        $retry = $this->owner->post(self::ORG.'/campaigns/'.$id.'/deliveries/retry');
        self::assertSame(200, $retry->status, $retry->body);
        self::assertSame(0, Json::int($retry->json(), 'retried'));
        self::assertSame(200, $this->owner->get(self::ORG.'/deliveries?state=failed')->status);
    }

    public function test_cancel_a_draft_and_campaign_errors(): void
    {
        $event = $this->createEvent();
        $id = Json::str($this->owner->post(self::ORG.'/campaigns', ['eventId' => $event, 'name' => 'Koncept', 'subject' => 'S', 'body' => 'B'])->json(), 'campaignId');

        $cancelled = $this->owner->post(self::ORG.'/campaigns/'.$id.'/cancel');
        self::assertSame(200, $cancelled->status, $cancelled->body);
        self::assertSame('cancelled', Json::str($cancelled->json(), 'state'));

        $this->assertProblem($this->owner->post(self::ORG.'/campaigns', ['eventId' => $event, 'name' => 'Bez textu']), 422, 'invalid_campaign');
        $this->assertProblem($this->owner->post(self::ORG.'/campaigns', ['eventId' => $event, 'name' => 'Špatný text', 'subject' => 'S', 'body' => '{{ x.y }}']), 422, 'template_invalid');
        $this->assertProblem($this->owner->post(self::ORG.'/campaigns', ['eventId' => $event, 'name' => 'Stav', 'subject' => 'S', 'body' => 'B', 'audience' => ['participationStates' => ['galaxy']]]), 422, 'validation_failed');
        $this->assertProblem($this->owner->post(self::ORG.'/campaigns', ['eventId' => 'nope', 'name' => 'x', 'subject' => 'S', 'body' => 'B']), 422, 'validation_failed');
        $this->assertProblem($this->owner->post(self::ORG.'/campaigns', ['eventId' => self::UNKNOWN, 'name' => 'x', 'subject' => 'S', 'body' => 'B']), 404, 'event_not_found');
        $this->assertProblem($this->owner->get(self::ORG.'/campaigns/'.self::UNKNOWN), 404, 'campaign_not_found');
        $this->assertProblem($this->owner->post(self::ORG.'/campaigns/'.self::UNKNOWN.'/send'), 404, 'campaign_not_found');
        $this->assertProblem($this->owner->post(self::ORG.'/campaigns/'.self::UNKNOWN.'/deliveries/retry'), 404, 'campaign_not_found');
        $this->assertProblem($this->owner->get(self::ORG.'/campaigns/'.self::UNKNOWN.'/deliveries'), 404, 'campaign_not_found');
    }

    public function test_delivery_errors(): void
    {
        $this->assertProblem($this->owner->get(self::ORG.'/deliveries/'.self::UNKNOWN), 404, 'delivery_not_found');
        $this->assertProblem($this->owner->post(self::ORG.'/deliveries/'.self::UNKNOWN.'/retry'), 404, 'delivery_not_found');
        $this->assertProblem($this->owner->get(self::ORG.'/deliveries?state=galaxy'), 422, 'validation_failed');
    }

    public function test_reminder_settings_and_suppressions(): void
    {
        $defaults = $this->owner->get(self::ORG.'/communication/reminder-settings');
        self::assertSame(200, $defaults->status, $defaults->body);

        $changed = $this->owner->request('PUT', self::ORG.'/communication/reminder-settings', ['paymentOffsetsHours' => [-24, -96, 48], 'preEventHours' => 12]);
        self::assertSame(200, $changed->status, $changed->body);
        self::assertSame([-96, -24, 48], Json::arr($changed->json(), 'paymentOffsetsHours'));
        self::assertSame(12, Json::int($changed->json(), 'preEventHours'));
        self::assertTrue(Json::at($changed->json(), 'custom'));
        $this->assertProblem($this->owner->request('PUT', self::ORG.'/communication/reminder-settings', ['paymentOffsetsHours' => [-5000], 'preEventHours' => 12]), 422, 'validation_failed');
        $this->assertProblem($this->owner->request('PUT', self::ORG.'/communication/reminder-settings', ['paymentOffsetsHours' => [1, 2, 3, 4, 5, 6], 'preEventHours' => 12]), 422, 'validation_failed');
        $this->owner->request('PUT', self::ORG.'/communication/reminder-settings', ['paymentOffsetsHours' => [-72, -24], 'preEventHours' => 24]);

        $email = 'optout-'.bin2hex(random_bytes(3)).'@example.test';
        $added = $this->owner->post(self::ORG.'/suppressions', ['email' => $email, 'scope' => 'marketing']);
        self::assertSame(201, $added->status, $added->body);
        $id = Json::str($added->json(), 'suppressionId');
        self::assertSame($id, Json::str($this->owner->post(self::ORG.'/suppressions', ['email' => strtoupper($email), 'scope' => 'marketing'])->json(), 'suppressionId'), 'idempotent, case-insensitive');
        $list = $this->owner->get(self::ORG.'/suppressions')->json();
        self::assertContains($id, Json::column(Json::at($list, 'items'), 'id'));
        self::assertStringNotContainsString($email, (string) json_encode($list), 'only hashes are stored');

        self::assertSame(204, $this->owner->request('DELETE', self::ORG.'/suppressions/'.$id)->status);
        $this->assertProblem($this->owner->request('DELETE', self::ORG.'/suppressions/'.$id), 404, 'suppression_not_found');
        $this->assertProblem($this->owner->post(self::ORG.'/suppressions', ['email' => 'not-an-email']), 422, 'validation_failed');
    }

    public function test_authentication_and_tenant_isolation(): void
    {
        $anonymous = $this->anonymous();
        $this->assertProblem($anonymous->get(self::ORG.'/message-templates'), 401, 'unauthorized');
        $this->assertProblem($anonymous->get(self::ORG.'/campaigns'), 401, 'unauthorized');
        $this->assertProblem($anonymous->get(self::ORG.'/deliveries'), 401, 'unauthorized');
        $this->assertProblem($anonymous->post(self::ORG.'/suppressions', ['email' => 'a@example.test']), 401, 'unauthorized');

        // Beta's owner asking for Alpha's organizer is told it does not exist (404, never 403, never data).
        $beta = $this->loginAs(ApiFixtures::BETA);
        $campaign = Json::str($this->owner->post(self::ORG.'/campaigns', ['eventId' => $this->createEvent(), 'name' => 'Cizí', 'subject' => 'S', 'body' => 'B'])->json(), 'campaignId');
        foreach (['/message-templates', '/campaigns', '/campaigns/'.$campaign, '/deliveries', '/suppressions', '/communication/reminder-settings'] as $path) {
            $this->assertProblem($beta->get(self::ORG.$path), 404, 'not_found');
        }
        // And inside his own organizer the campaign of Alpha does not exist either.
        $this->assertProblem($beta->get('/api/admin/org/'.ApiFixtures::BETA['slug'].'/campaigns/'.$campaign), 404, 'campaign_not_found');
        $this->assertProblem($beta->post('/api/admin/org/'.ApiFixtures::BETA['slug'].'/campaigns/'.$campaign.'/send'), 404, 'campaign_not_found');
    }

    private function createEvent(): string
    {
        $response = $this->owner->post(self::ORG.'/events', ['slug' => 'komunikace-'.bin2hex(random_bytes(5)), 'mode' => 'multi', 'details' => [
            'name' => 'Borský podzimní víkend 2026',
            'shortDescription' => 'Podzimní závody',
            'description' => 'Sobota a neděle v Plzni.',
            'startsAt' => '2026-11-21T08:00:00+01:00',
            'endsAt' => '2026-11-22T16:00:00+01:00',
            'timezone' => 'Europe/Prague',
            'contactName' => 'Jana Nováková',
            'contactEmail' => 'jana@example.test',
            'venues' => [['name' => 'Borský park', 'address' => 'Plzeň', 'mapUrl' => 'https://mapy.cz/borsky-park']],
        ]]);
        self::assertSame(201, $response->status, $response->body);
        $eventId = Json::str($response->json(), 'eventId');

        // Campaigns target published events only: give it an activity with a variant, cash payment, and publish.
        $activity = $this->owner->post(self::ORG.'/events/'.$eventId.'/activities', ['name' => 'Trail 5 km', 'startsAt' => '2026-11-21T10:00:00+01:00', 'capacityLimit' => 100]);
        self::assertSame(201, $activity->status, $activity->body);
        $variant = $this->owner->post(self::ORG.'/events/'.$eventId.'/activities/'.Json::str($activity->json(), 'activityId').'/variants', ['name' => 'Jednotlivec', 'price' => ['minor' => 30000, 'currency' => 'CZK'], 'capacityLimit' => 100]);
        self::assertSame(201, $variant->status, $variant->body);
        self::assertSame(200, $this->owner->request('PUT', self::ORG.'/events/'.$eventId.'/payment-rules', ['methods' => ['cash'], 'dueDays' => 7, 'holdTtlHours' => 48])->status);
        $published = $this->owner->post(self::ORG.'/events/'.$eventId.'/publish');
        self::assertSame(200, $published->status, $published->body);

        return $eventId;
    }
}
