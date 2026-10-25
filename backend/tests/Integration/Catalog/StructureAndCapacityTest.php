<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Catalog;

use Eventor\Identity\Domain\Membership\Role;
use Eventor\Tests\Support\Catalog\CatalogHttpTestCase;

/**
 * EVT-05, EVT-06 and CAP-06: activities, variants with prices (Money), the capacity limits and their buckets.
 */
final class StructureAndCapacityTest extends CatalogHttpTestCase
{
    public function test_activities_and_variants_are_added_updated_and_removed_in_a_draft(): void
    {
        $this->login(self::OWNER);
        $created = $this->createEvent();
        $eventId = (string) $created['eventId'];
        $base = self::BASE.'/events/'.$eventId;

        $activityId = $this->addActivity($eventId, 'Trail 10 km', ['minAge' => 15, 'startsAt' => '2026-11-21T11:00:00+01:00', 'description' => 'Trať 10 km']);
        $variantId = $this->addVariant($eventId, $activityId, 'Jednotlivec', 40000, 150);

        $event = $this->request('GET', $base);
        self::assertIsArray($event);
        $activity = $event['activities'][0];
        self::assertSame(15, $activity['minAge']);
        self::assertFalse($activity['implicit']);
        self::assertSame(40000, $activity['variants'][0]['price']['minor']);
        self::assertSame('CZK', $activity['variants'][0]['price']['currency']);
        self::assertSame(150, $activity['variants'][0]['capacityLimit']);
        self::assertSame(1, $event['priceListVersion'], 'a draft has no price list history');

        $this->request('PUT', $base.'/activities/'.$activityId, ['name' => 'Trail 10 km (hlavní)', 'minAge' => 16, 'minorsOnly' => false], ['If-Match' => (string) $activity['version']]);
        self::assertResponseIsSuccessful();
        $this->request('PUT', $base.'/variants/'.$variantId, ['name' => 'Jednotlivec', 'price' => ['minor' => 45000, 'currency' => 'CZK']]);
        $updated = $this->request('GET', $base);
        self::assertIsArray($updated);
        self::assertSame('Trail 10 km (hlavní)', $updated['activities'][0]['name']);
        self::assertSame(16, $updated['activities'][0]['minAge']);
        self::assertSame(45000, $updated['activities'][0]['variants'][0]['price']['minor']);
        self::assertSame(1, $updated['priceListVersion']);

        $this->request('DELETE', $base.'/variants/'.$variantId);
        self::assertResponseIsSuccessful();
        $this->request('DELETE', $base.'/activities/'.$activityId);
        self::assertResponseIsSuccessful();
        $empty = $this->request('GET', $base);
        self::assertIsArray($empty);
        self::assertSame([], $empty['activities']);
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM audit.audit_entry WHERE action = 'activity.removed' AND entity_id = ?", [$activityId]));
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM audit.audit_entry WHERE action = 'variant.removed' AND entity_id = ?", [$variantId]));
    }

    public function test_removing_an_activity_removes_its_variants_too(): void
    {
        $this->login(self::OWNER);
        $created = $this->createEvent();
        $eventId = (string) $created['eventId'];
        $activityId = $this->addActivity($eventId, 'Canicross');
        $this->addVariant($eventId, $activityId, 'Jednotlivec', 35000);
        $this->addVariant($eventId, $activityId, 'Štěně', 15000);

        $this->request('DELETE', self::BASE.'/events/'.$eventId.'/activities/'.$activityId);

        self::assertResponseIsSuccessful();
        self::assertSame(0, $this->dbInt('SELECT count(*) FROM catalog.variant WHERE event_id = ?', [$eventId]));
    }

    public function test_validation_and_not_found_errors(): void
    {
        $this->login(self::OWNER);
        $created = $this->createEvent();
        $eventId = (string) $created['eventId'];
        $activityId = $this->addActivity($eventId, 'Trail');
        $base = self::BASE.'/events/'.$eventId;

        $this->request('POST', $base.'/activities', ['name' => '', 'minAge' => 200]);
        $problem = $this->assertProblem(422, 'validation_failed');
        self::assertEqualsCanonicalizing(['name', 'minAge'], array_column($problem['violations'], 'path'));

        $this->request('POST', $base.'/activities', ['name' => 'Špatný věk', 'minAge' => 18, 'maxAge' => 15]);
        $this->assertProblem(422, 'invalid_catalog_data');

        $this->request('POST', $base.'/activities/'.$activityId.'/variants', ['name' => 'Záporná', 'price' => ['minor' => -5, 'currency' => 'CZK']]);
        $this->assertProblem(422, 'invalid_catalog_data');

        $this->request('POST', $base.'/activities/'.$activityId.'/variants', ['name' => 'Jiná měna', 'price' => ['minor' => 100, 'currency' => 'XXX']]);
        $this->assertProblem(422, 'validation_failed');

        $this->request('POST', $base.'/activities/0197b4a0-0000-7000-8000-000000000000/variants', ['name' => 'Nikam', 'price' => ['minor' => 100, 'currency' => 'CZK']]);
        $this->assertProblem(404, 'activity_not_found');
        $this->request('PUT', $base.'/variants/0197b4a0-0000-7000-8000-000000000000', ['name' => 'x', 'price' => ['minor' => 100, 'currency' => 'CZK']]);
        $this->assertProblem(404, 'variant_not_found');
    }

    public function test_a_stale_activity_edit_is_a_409(): void
    {
        $this->login(self::OWNER);
        $created = $this->createEvent();
        $eventId = (string) $created['eventId'];
        $activityId = $this->addActivity($eventId, 'Trail');

        $this->request('PUT', self::BASE.'/events/'.$eventId.'/activities/'.$activityId, ['name' => 'Trail 2'], ['If-Match' => '5']);

        $this->assertProblem(409, 'version_conflict');
    }

    public function test_a_check_in_operator_cannot_change_the_structure(): void
    {
        $this->addMember('obsluha@example.test', Role::CheckIn);
        $this->login(self::OWNER);
        $created = $this->createEvent();
        $eventId = (string) $created['eventId'];
        $activityId = $this->addActivity($eventId, 'Trail');

        $this->login('obsluha@example.test');
        $this->request('POST', self::BASE.'/events/'.$eventId.'/activities', ['name' => 'Nová']);
        $this->assertProblem(403, 'forbidden');
        $this->request('DELETE', self::BASE.'/events/'.$eventId.'/activities/'.$activityId);
        $this->assertProblem(403, 'forbidden');
        $this->request('POST', self::BASE.'/events/'.$eventId.'/capacity', ['scope' => 'event', 'limit' => 10]);
        $this->assertProblem(403, 'forbidden');
    }

    public function test_the_capacity_of_a_draft_is_configuration_only(): void
    {
        $this->login(self::OWNER);
        $event = $this->createPublishableEvent();
        $base = self::BASE.'/events/'.$event['eventId'].'/capacity';

        $result = $this->request('POST', $base, ['scope' => 'variant', 'scopeId' => $event['trailVariant'], 'limit' => 80]);

        self::assertResponseIsSuccessful();
        self::assertIsArray($result);
        self::assertFalse($result['belowOccupancy']);
        self::assertSame([], $result['warnings']);
        self::assertSame(80, $result['event']['activities'][0]['variants'][0]['capacityLimit']);
        self::assertSame(0, $this->dbInt('SELECT count(*) FROM registration.capacity_bucket WHERE event_id = ?', [$event['eventId']]), 'no bucket before the publication');

        $this->request('POST', $base, ['scope' => 'event', 'limit' => 200]);
        self::assertResponseIsSuccessful();
        $this->request('POST', $base, ['scope' => 'activity', 'scopeId' => $event['relay'], 'limit' => null]);
        self::assertResponseIsSuccessful();
    }

    public function test_the_capacity_of_a_published_event_is_changed_in_its_bucket_with_a_reason(): void
    {
        $this->login(self::OWNER);
        $event = $this->createPublishableEvent();
        $this->publish($event['eventId']);
        $base = self::BASE.'/events/'.$event['eventId'].'/capacity';
        $bucket = static fn(string $variant): string => "SELECT \"limit\" FROM registration.capacity_bucket WHERE scope_type = 'variant' AND scope_id = '".$variant."'";

        $this->request('POST', $base, ['scope' => 'variant', 'scopeId' => $event['trailVariant'], 'limit' => 120]);
        $problem = $this->assertProblem(422, 'invalid_catalog_data');
        self::assertStringContainsString('reason', (string) $problem['detail']);

        $result = $this->request('POST', $base, ['scope' => 'variant', 'scopeId' => $event['trailVariant'], 'limit' => 120, 'reason' => 'Větší louka']);

        self::assertResponseIsSuccessful();
        self::assertIsArray($result);
        self::assertFalse($result['belowOccupancy']);
        self::assertSame(120, $this->dbInt($bucket($event['trailVariant'])));
        self::assertSame(120, $result['event']['activities'][0]['variants'][0]['capacityLimit']);
        $audit = $this->dbRow("SELECT reason, before, after FROM audit.audit_entry WHERE action = 'capacity.limit_changed' AND entity_id = ?", [$event['trailVariant']]);
        self::assertSame('Větší louka', $audit['reason']);
        self::assertSame(['limit' => 100], json_decode((string) $audit['before'], true));
    }

    public function test_lowering_below_the_occupancy_warns_and_never_cancels(): void
    {
        $this->login(self::OWNER);
        $event = $this->createPublishableEvent();
        $this->publish($event['eventId']);
        $this->connection()->executeStatement("UPDATE registration.capacity_bucket SET held = 4, confirmed = 20 WHERE scope_type = 'variant' AND scope_id = ?", [$event['trailVariant']]);

        $result = $this->request('POST', self::BASE.'/events/'.$event['eventId'].'/capacity', ['scope' => 'variant', 'scopeId' => $event['trailVariant'], 'limit' => 10, 'reason' => 'Zmenšená trať']);

        self::assertResponseIsSuccessful();
        self::assertIsArray($result);
        self::assertTrue($result['belowOccupancy']);
        self::assertSame(['capacity_below_occupancy'], $result['warnings']);
        $row = $this->dbRow("SELECT \"limit\", held, confirmed, overbooked_allowed FROM registration.capacity_bucket WHERE scope_type = 'variant' AND scope_id = ?", [$event['trailVariant']]);
        self::assertSame(10, $row['limit']);
        self::assertSame([4, 20], [$row['held'], $row['confirmed']], 'nobody is cancelled');
        self::assertTrue((bool) $row['overbooked_allowed']);
    }

    public function test_capacity_scopes_are_checked(): void
    {
        $this->login(self::OWNER);
        $event = $this->createPublishableEvent();
        $base = self::BASE.'/events/'.$event['eventId'].'/capacity';

        $this->request('POST', $base, ['scope' => 'galaxy', 'limit' => 5]);
        $this->assertProblem(422, 'validation_failed');
        $this->request('POST', $base, ['scope' => 'variant', 'limit' => 5]);
        $this->assertProblem(422, 'invalid_catalog_data');
        $this->request('POST', $base, ['scope' => 'variant', 'scopeId' => '0197b4a0-0000-7000-8000-000000000000', 'limit' => 5]);
        $this->assertProblem(404, 'variant_not_found');
        $this->request('POST', $base, ['scope' => 'event', 'limit' => -1]);
        $this->assertProblem(422, 'validation_failed');
    }

    public function test_a_published_event_gets_buckets_for_new_activities_and_variants_and_a_new_price_list(): void
    {
        $this->login(self::OWNER);
        $event = $this->createPublishableEvent();
        $this->publish($event['eventId']);
        $eventId = $event['eventId'];

        $activityId = $this->addActivity($eventId, 'Canicross', ['capacityLimit' => 40]);
        $variantId = $this->addVariant($eventId, $activityId, 'Jednotlivec', 35000, 40);

        self::assertSame(40, $this->dbInt("SELECT \"limit\" FROM registration.capacity_bucket WHERE scope_type = 'activity' AND scope_id = ?", [$activityId]));
        self::assertSame(40, $this->dbInt("SELECT \"limit\" FROM registration.capacity_bucket WHERE scope_type = 'variant' AND scope_id = ?", [$variantId]));
        self::assertSame(2, $this->dbInt('SELECT price_list_version FROM catalog.event WHERE id = ?', [$eventId]));

        $this->request('PUT', self::BASE.'/events/'.$eventId.'/variants/'.$variantId, ['name' => 'Jednotlivec', 'price' => ['minor' => 36000, 'currency' => 'CZK']]);
        self::assertSame(3, $this->dbInt('SELECT price_list_version FROM catalog.event WHERE id = ?', [$eventId]));

        $this->request('PUT', self::BASE.'/events/'.$eventId.'/variants/'.$variantId, ['name' => 'Jednotlivec (nový název)', 'price' => ['minor' => 36000, 'currency' => 'CZK']]);
        self::assertSame(3, $this->dbInt('SELECT price_list_version FROM catalog.event WHERE id = ?', [$eventId]), 'a rename leaves the prices alone');
    }
}
