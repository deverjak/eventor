<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Registration\Capacity;

use Eventor\Identity\Domain\Membership\Role;
use Eventor\Registration\Contract\CapacityBucketSpec;
use Eventor\Registration\Contract\CapacityProvisioner;
use Eventor\Registration\Domain\Capacity\CapacityAllocator;
use Eventor\Registration\Domain\Capacity\ClaimKind;
use Eventor\Registration\Domain\Capacity\SeatClaim;
use Eventor\Shared\Domain\Capacity\CapacityScope;
use Eventor\Shared\Domain\Capacity\CapacityUnit;
use Eventor\Shared\Domain\Id\CapacityBucketId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Id\UserAccountId;
use Eventor\Tests\Support\Foundry\MembershipFactory;
use Eventor\Tests\Support\HttpTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * GET /api/admin/org/{orgSlug}/events/{eventId}/capacity (CAP-03): 200, 401, 403, 404.
 */
final class CapacityOverviewEndpointTest extends HttpTestCase
{
    use Factories;

    /**
     * @return array{organizer: OrganizerId, event: EventId, event_bucket: CapacityBucketId, activity_bucket: CapacityBucketId}
     */
    private function eventWithBuckets(string $organizerId): array
    {
        $organizer = OrganizerId::fromString($organizerId);
        $event = EventId::generate();
        $eventBucket = CapacityBucketId::generate();
        $activityBucket = CapacityBucketId::generate();
        $this->service(CapacityProvisioner::class)->provision($organizer, $event, [
            new CapacityBucketSpec($activityBucket, CapacityScope::Activity, EventId::generate()->toString(), 10, CapacityUnit::Seat),
            new CapacityBucketSpec($eventBucket, CapacityScope::Event, $event->toString(), null, CapacityUnit::Seat),
        ]);
        $participation = ParticipationId::generate();
        $this->service(CapacityAllocator::class)->claim($organizer, [
            new SeatClaim($participation, $activityBucket, 2, ClaimKind::Held),
            new SeatClaim($participation, $eventBucket, 2, ClaimKind::Held),
            new SeatClaim(ParticipationId::generate(), $activityBucket, 3, ClaimKind::Confirmed),
        ]);

        return ['organizer' => $organizer, 'event' => $event, 'event_bucket' => $eventBucket, 'activity_bucket' => $activityBucket];
    }

    public function test_a_member_sees_the_exact_counters_of_every_level(): void
    {
        $ids = $this->createOwner('jana@example.test', 'klub');
        $fixture = $this->eventWithBuckets($ids['organizerId']);
        $this->login('jana@example.test');

        $body = $this->request('GET', '/api/admin/org/klub/events/'.$fixture['event']->toString().'/capacity');

        self::assertResponseIsSuccessful();
        self::assertSame([
            'eventId' => $fixture['event']->toString(),
            'buckets' => [
                [
                    'bucketId' => $fixture['event_bucket']->toString(),
                    'scope' => 'event',
                    'scopeId' => $fixture['event']->toString(),
                    'unit' => 'seat',
                    'limit' => null,
                    'held' => 2,
                    'confirmed' => 0,
                    'available' => null,
                    'overbooked' => false,
                ],
                [
                    'bucketId' => $fixture['activity_bucket']->toString(),
                    'scope' => 'activity',
                    'scopeId' => $this->scopeIdOf($fixture['activity_bucket']),
                    'unit' => 'seat',
                    'limit' => 10,
                    'held' => 2,
                    'confirmed' => 3,
                    'available' => 5,
                    'overbooked' => false,
                ],
            ],
        ], $body);
    }

    public function test_it_reports_an_overbooked_bucket(): void
    {
        $ids = $this->createOwner('jana@example.test', 'klub');
        $fixture = $this->eventWithBuckets($ids['organizerId']);
        $this->service(CapacityProvisioner::class)->changeLimit($fixture['organizer'], $fixture['activity_bucket'], 2, 'shrunk');
        $this->login('jana@example.test');

        $body = $this->request('GET', '/api/admin/org/klub/events/'.$fixture['event']->toString().'/capacity');

        self::assertIsArray($body);
        self::assertIsArray($body['buckets']);
        $activity = $body['buckets'][1];
        self::assertIsArray($activity);
        self::assertTrue($activity['overbooked']);
        self::assertSame(0, $activity['available']);
    }

    public function test_anonymous_gets_401(): void
    {
        $this->createOwner('jana@example.test', 'klub');

        $this->request('GET', '/api/admin/org/klub/events/'.EventId::generate()->toString().'/capacity');

        $this->assertProblem(401, 'unauthorized');
    }

    public function test_a_member_without_event_configure_gets_403(): void
    {
        $owner = $this->createOwner('jana@example.test', 'klub');
        $fixture = $this->eventWithBuckets($owner['organizerId']);
        $finance = $this->createOwner('fin@example.test', 'fin-own-club');
        MembershipFactory::createOne([
            'userAccountId' => UserAccountId::fromString($finance['userAccountId']),
            'organizerId' => OrganizerId::fromString($owner['organizerId']),
            'role' => Role::Finance,
        ]);
        $this->login('fin@example.test');

        $this->request('GET', '/api/admin/org/klub/events/'.$fixture['event']->toString().'/capacity');

        $this->assertProblem(403, 'forbidden');
    }

    public function test_an_event_without_buckets_is_404(): void
    {
        $this->createOwner('jana@example.test', 'klub');
        $this->login('jana@example.test');

        $this->request('GET', '/api/admin/org/klub/events/'.EventId::generate()->toString().'/capacity');

        $this->assertProblem(404, 'capacity_bucket_not_found');
    }

    public function test_a_malformed_event_id_is_404_not_a_server_error(): void
    {
        $this->createOwner('jana@example.test', 'klub');
        $this->login('jana@example.test');

        $this->request('GET', '/api/admin/org/klub/events/not-a-uuid/capacity');

        $this->assertProblem(404, 'capacity_bucket_not_found');
    }

    public function test_the_buckets_of_a_foreign_organizers_event_are_404_even_with_the_right_event_id(): void
    {
        $this->createOwner('jana@example.test', 'klub');
        $petr = $this->createOwner('petr@example.test', 'cizi-klub');
        $foreign = $this->eventWithBuckets($petr['organizerId']);
        $this->login('jana@example.test');

        $this->request('GET', '/api/admin/org/klub/events/'.$foreign['event']->toString().'/capacity');
        $this->assertProblem(404, 'capacity_bucket_not_found');

        $this->request('GET', '/api/admin/org/cizi-klub/events/'.$foreign['event']->toString().'/capacity');
        $this->assertProblem(404, 'not_found');
    }

    private function scopeIdOf(CapacityBucketId $bucket): string
    {
        return $this->dbString('SELECT scope_id FROM registration.capacity_bucket WHERE id = ?', [$bucket->toString()]);
    }
}
