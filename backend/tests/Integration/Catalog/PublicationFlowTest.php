<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Catalog;

use Eventor\Identity\Domain\Membership\Role;
use Eventor\Tests\Support\Catalog\CatalogHttpTestCase;

/**
 * EVT-03 and EVT-04 (chapter 05) end to end: the publication rules, the transaction of the publication (form
 * version, capacity buckets, audit) and the transitions of both state machines with their guards.
 */
final class PublicationFlowTest extends CatalogHttpTestCase
{
    public function test_an_incomplete_event_is_refused_with_every_broken_rule(): void
    {
        $this->login(self::OWNER);
        $created = $this->createEvent();
        $eventId = (string) $created['eventId'];

        // No activity at all, a paid-looking structure is not even there yet.
        $this->request('POST', self::BASE.'/events/'.$eventId.'/publish');
        $problem = $this->assertProblem(422, 'event_not_publishable');
        self::assertSame(['no_activity'], array_column($problem['violations'], 'code'));

        // An activity without a variant, a paid variant without a payment method, a transfer without Pairrot.
        $activity = $this->addActivity($eventId, 'Trail 5 km');
        $this->request('POST', self::BASE.'/events/'.$eventId.'/publish');
        $problem = $this->assertProblem(422, 'event_not_publishable');
        self::assertSame(['activity_without_variant'], array_column($problem['violations'], 'code'));

        $this->addVariant($eventId, $activity, 'Jednotlivec', 30000);
        $this->request('POST', self::BASE.'/events/'.$eventId.'/publish');
        $problem = $this->assertProblem(422, 'event_not_publishable');
        self::assertSame(['payment_method_required'], array_column($problem['violations'], 'code'));

        $this->request('PUT', self::BASE.'/events/'.$eventId.'/payment-rules', ['methods' => ['bank_transfer']]);
        $this->request('POST', self::BASE.'/events/'.$eventId.'/publish');
        $problem = $this->assertProblem(422, 'event_not_publishable');
        self::assertSame(['transfer_requires_pairrot'], array_column($problem['violations'], 'code'));

        self::assertSame('draft', $this->dbString('SELECT publication_state FROM catalog.event WHERE id = ?', [$eventId]));
        self::assertSame(0, $this->dbInt('SELECT count(*) FROM registration.capacity_bucket WHERE event_id = ?', [$eventId]));
    }

    public function test_publishing_creates_the_form_version_the_buckets_and_the_audit(): void
    {
        $this->login(self::OWNER);
        $event = $this->createPublishableEvent();

        $this->request('POST', self::BASE.'/events/'.$event['eventId'].'/publish');

        self::assertResponseIsSuccessful();
        $result = json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(1, $result['formVersion']);
        self::assertSame('published', $this->dbString('SELECT publication_state FROM catalog.event WHERE id = ?', [$event['eventId']]));

        // The form: version 1 is frozen with a hash, no draft is left.
        $form = $this->dbRow('SELECT id, number, published_at, schema_sha256 FROM catalog.form_version WHERE event_id = ?', [$event['eventId']]);
        self::assertSame($result['formVersionId'], $form['id']);
        self::assertNotNull($form['published_at']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) $form['schema_sha256']);

        // Capacity: one bucket for the event, two activities and two variants, with the configured limits and units.
        self::assertSame(5, $this->dbInt('SELECT count(*) FROM registration.capacity_bucket WHERE event_id = ?', [$event['eventId']]));
        self::assertSame(100, $this->dbInt("SELECT \"limit\" FROM registration.capacity_bucket WHERE scope_type = 'variant' AND scope_id = ?", [$event['trailVariant']]));
        self::assertSame('team', $this->dbString("SELECT unit FROM registration.capacity_bucket WHERE scope_type = 'variant' AND scope_id = ?", [$event['relayVariant']]));
        self::assertSame(100, $this->dbInt("SELECT \"limit\" FROM registration.capacity_bucket WHERE scope_type = 'activity' AND scope_id = ?", [$event['trail']]));
        self::assertNull($this->dbRow("SELECT \"limit\" FROM registration.capacity_bucket WHERE scope_type = 'event' AND scope_id = ?", [$event['eventId']])['limit']);
        self::assertSame(
            $this->dbString('SELECT capacity_bucket_id FROM catalog.variant WHERE id = ?', [$event['trailVariant']]),
            $this->dbString("SELECT id FROM registration.capacity_bucket WHERE scope_type = 'variant' AND scope_id = ?", [$event['trailVariant']]),
            'Catalog decides the bucket id, Registration creates the row with it',
        );

        // Audit: the publication, the frozen version, by the signed-in member.
        $audit = $this->dbRow("SELECT actor_type, actor_id, before, after FROM audit.audit_entry WHERE action = 'event.published' AND entity_id = ?", [$event['eventId']]);
        self::assertSame('USER', $audit['actor_type']);
        self::assertSame($this->owner['userAccountId'], $audit['actor_id']);
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM audit.audit_entry WHERE action = 'form.version_published' AND entity_id = ?", [$form['id']]));
    }

    public function test_publishing_twice_is_a_409(): void
    {
        $this->login(self::OWNER);
        $event = $this->createPublishableEvent();
        $this->publish($event['eventId']);

        $this->request('POST', self::BASE.'/events/'.$event['eventId'].'/publish');

        $problem = $this->assertProblem(409, 'transition_not_allowed');
        self::assertSame('published', $problem['context']['state']);
    }

    public function test_a_failed_publication_leaves_nothing_behind(): void
    {
        $this->login(self::OWNER);
        $event = $this->createPublishableEvent();
        $this->request('PUT', self::BASE.'/events/'.$event['eventId'].'/payment-rules', ['methods' => []]);

        $this->request('POST', self::BASE.'/events/'.$event['eventId'].'/publish');

        $this->assertProblem(422, 'event_not_publishable');
        self::assertSame(0, $this->dbInt('SELECT count(*) FROM registration.capacity_bucket WHERE event_id = ?', [$event['eventId']]));
        self::assertSame(0, $this->dbInt('SELECT count(*) FROM catalog.form_version WHERE event_id = ? AND published_at IS NOT NULL', [$event['eventId']]));
    }

    public function test_publishing_needs_event_configure_and_a_known_event(): void
    {
        $this->addMember('finance@example.test', Role::Finance);
        $this->login(self::OWNER);
        $event = $this->createPublishableEvent();

        $this->login('finance@example.test');
        $this->request('POST', self::BASE.'/events/'.$event['eventId'].'/publish');
        $this->assertProblem(403, 'forbidden');
        self::assertSame('draft', $this->dbString('SELECT publication_state FROM catalog.event WHERE id = ?', [$event['eventId']]));
    }

    public function test_registration_opens_in_order_event_first_then_activities(): void
    {
        $this->login(self::OWNER);
        $event = $this->createPublishableEvent();
        $base = self::BASE.'/events/'.$event['eventId'];

        // A draft cannot open registration.
        $this->request('POST', $base.'/registration/open');
        $this->assertProblem(409, 'transition_not_allowed');

        $this->publish($event['eventId']);

        // An activity cannot open before the event does.
        $this->request('POST', $base.'/activities/'.$event['trail'].'/registration/open');
        $problem = $this->assertProblem(409, 'transition_not_allowed');
        self::assertStringContainsString('registration of the event is open', (string) $problem['detail']);

        $this->request('POST', $base.'/registration/open');
        self::assertResponseIsSuccessful();
        $this->request('POST', $base.'/activities/'.$event['trail'].'/registration/open');
        $detail = $this->request('GET', $base);
        self::assertIsArray($detail);
        self::assertSame('open', $detail['registrationState']);
        self::assertSame(['open', 'not_open'], array_column($detail['activities'], 'registrationState'));

        // pause / resume of one activity, an illegal repetition, close.
        $this->request('POST', $base.'/activities/'.$event['trail'].'/registration/pause', ['reason' => 'Rozbité značení']);
        self::assertResponseIsSuccessful();
        $this->request('POST', $base.'/activities/'.$event['trail'].'/registration/pause');
        $this->assertProblem(409, 'transition_not_allowed');
        $this->request('POST', $base.'/activities/'.$event['trail'].'/registration/resume');
        self::assertResponseIsSuccessful();
        $this->request('POST', $base.'/registration/close', ['cascade' => true]);
        $closed = $this->request('GET', $base);
        self::assertIsArray($closed);
        self::assertSame('closed', $closed['registrationState']);
        self::assertSame(['closed', 'closed'], array_column($closed['activities'], 'registrationState'));

        // A closed registration stays closed; every change was audited with its reason.
        $this->request('POST', $base.'/registration/open');
        $this->assertProblem(409, 'transition_not_allowed');
        self::assertSame('Rozbité značení', $this->dbString("SELECT reason FROM audit.audit_entry WHERE action = 'registration.paused' AND entity_id = ?", [$event['trail']]));
    }

    public function test_cascade_opening_opens_every_activity(): void
    {
        $this->login(self::OWNER);
        $event = $this->createPublishableEvent();
        $this->publish($event['eventId']);

        $this->request('POST', self::BASE.'/events/'.$event['eventId'].'/registration/open', ['cascade' => true]);

        $detail = $this->request('GET', self::BASE.'/events/'.$event['eventId']);
        self::assertIsArray($detail);
        self::assertSame(['open', 'open'], array_column($detail['activities'], 'registrationState'));
    }

    public function test_the_owner_cancels_with_a_reason_and_a_confirmation_and_registration_closes(): void
    {
        $this->login(self::OWNER);
        $event = $this->createPublishableEvent();
        $this->publish($event['eventId']);
        $this->request('POST', self::BASE.'/events/'.$event['eventId'].'/registration/open', ['cascade' => true]);
        $uri = self::BASE.'/events/'.$event['eventId'].'/cancel';

        $this->request('POST', $uri, ['reason' => '', 'confirmed' => false]);
        $problem = $this->assertProblem(422, 'validation_failed');
        self::assertEqualsCanonicalizing(['reason', 'confirmed'], array_column($problem['violations'], 'path'));

        $cancelled = $this->request('POST', $uri, ['reason' => 'Bouřka', 'confirmed' => true]);

        self::assertResponseIsSuccessful();
        self::assertIsArray($cancelled);
        self::assertSame('cancelled', $cancelled['publicationState']);
        self::assertSame('Bouřka', $cancelled['cancellationReason']);
        self::assertSame('closed', $cancelled['registrationState']);
        self::assertSame(['closed', 'closed'], array_column($cancelled['activities'], 'registrationState'));
        self::assertSame('Bouřka', $this->dbString("SELECT reason FROM audit.audit_entry WHERE action = 'event.cancelled' AND entity_id = ?", [$event['eventId']]));

        $this->request('POST', $uri, ['reason' => 'Ještě jednou', 'confirmed' => true]);
        $this->assertProblem(409, 'transition_not_allowed');
    }

    public function test_an_event_manager_cannot_cancel(): void
    {
        $this->addMember('manager@example.test', Role::EventManager);
        $this->login(self::OWNER);
        $event = $this->createPublishableEvent();
        $this->publish($event['eventId']);

        $this->login('manager@example.test');
        $this->request('POST', self::BASE.'/events/'.$event['eventId'].'/cancel', ['reason' => 'x', 'confirmed' => true]);

        $this->assertProblem(403, 'forbidden');
        self::assertSame('published', $this->dbString('SELECT publication_state FROM catalog.event WHERE id = ?', [$event['eventId']]));
    }

    public function test_an_event_can_be_completed_only_after_it_ended_and_then_archived(): void
    {
        $this->login(self::OWNER);
        $event = $this->createPublishableEvent();
        $base = self::BASE.'/events/'.$event['eventId'];

        $this->request('POST', $base.'/complete');
        $this->assertProblem(409, 'transition_not_allowed');
        $this->publish($event['eventId']);

        // The guard asks the clock: the event ends on 22 November 2026.
        $this->request('POST', $base.'/complete');
        $problem = $this->assertProblem(409, 'transition_not_allowed');
        self::assertStringContainsString('has not ended', (string) $problem['detail']);

        $this->request('POST', $base.'/archive');
        $this->assertProblem(409, 'transition_not_allowed');

        // Let the event end: only the stored dates move, the rules stay the same.
        $this->connection()->executeStatement("UPDATE catalog.event SET starts_at = '2020-01-01 08:00:00+00', ends_at = '2020-01-02 08:00:00+00' WHERE id = ?", [$event['eventId']]);
        $this->forgetLoadedEntities();

        $completed = $this->request('POST', $base.'/complete');
        self::assertResponseIsSuccessful();
        self::assertIsArray($completed);
        self::assertSame('completed', $completed['publicationState']);
        self::assertSame('closed', $completed['registrationState']);

        $archived = $this->request('POST', $base.'/archive');
        self::assertResponseIsSuccessful();
        self::assertIsArray($archived);
        self::assertSame('archived', $archived['publicationState']);
    }

    public function test_structure_of_a_published_event_cannot_be_removed(): void
    {
        $this->login(self::OWNER);
        $event = $this->createPublishableEvent();
        $this->publish($event['eventId']);
        $base = self::BASE.'/events/'.$event['eventId'];

        $this->request('DELETE', $base.'/activities/'.$event['trail']);
        $this->assertProblem(409, 'event_not_draft');
        $this->request('DELETE', $base.'/variants/'.$event['trailVariant']);
        $this->assertProblem(409, 'event_not_draft');
    }
}
