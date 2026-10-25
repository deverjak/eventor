<?php

declare(strict_types=1);

namespace Eventor\Tests\Api\Audit;

use Eventor\Tests\Api\Support\ApiClient;
use Eventor\Tests\Api\Support\ApiFixtures;
use Eventor\Tests\Api\Support\ApiTestCase;

/**
 * AUD-04 over real HTTP. The seeded organizer was created by the console, so its own registration is in the log.
 */
final class AuditApiTest extends ApiTestCase
{
    public function test_the_history_of_the_organizer_shows_who_created_it(): void
    {
        $client = $this->loginAs(ApiFixtures::ALPHA);
        $organizerId = $this->organizerIdOf($client, 'api-alpha');

        $response = $client->get('/api/admin/org/api-alpha/audit?entityType=organizer&entityId='.$organizerId);

        self::assertSame(200, $response->status, $response->body);
        /** @var array{total: int, items: list<array{action: string, entityId: string, actor: array{type: string}}>} $body */
        $body = $response->json();
        self::assertGreaterThanOrEqual(1, $body['total']);
        self::assertSame('organizer.registered', $body['items'][0]['action']);
        self::assertSame('SYSTEM', $body['items'][0]['actor']['type']);
        self::assertSame($organizerId, $body['items'][0]['entityId']);
    }

    public function test_the_history_filters_by_actor_kind(): void
    {
        $client = $this->loginAs(ApiFixtures::ALPHA);
        $organizerId = $this->organizerIdOf($client, 'api-alpha');

        $system = $client->get('/api/admin/org/api-alpha/audit?entityType=organizer&entityId='.$organizerId.'&actor=system');
        $device = $client->get('/api/admin/org/api-alpha/audit?entityType=organizer&entityId='.$organizerId.'&actor=device');

        self::assertSame(1, $system->json()['total'] ?? null);
        self::assertSame(0, $device->json()['total'] ?? null);
    }

    public function test_the_owner_reads_the_organizer_wide_log_and_never_a_neighbours(): void
    {
        $alpha = $this->loginAs(ApiFixtures::ALPHA);
        $betaId = $this->organizerIdOf($this->loginAs(ApiFixtures::BETA), 'api-beta');

        $response = $alpha->get('/api/admin/org/api-alpha/audit-log?perPage=200');

        self::assertSame(200, $response->status, $response->body);
        /** @var array{total: int, perPage: int, items: list<array{entityId: string}>} $body */
        $body = $response->json();
        self::assertGreaterThanOrEqual(2, $body['total']);
        self::assertSame(200, $body['perPage']);
        self::assertNotContains($betaId, array_column($body['items'], 'entityId'));
    }

    public function test_parameters_are_validated(): void
    {
        $client = $this->loginAs(ApiFixtures::ALPHA);

        $this->assertProblem($client->get('/api/admin/org/api-alpha/audit'), 422, 'validation_failed');
        $this->assertProblem($client->get('/api/admin/org/api-alpha/audit?entityType=event'), 422, 'validation_failed');
        $this->assertProblem($client->get('/api/admin/org/api-alpha/audit?entityType=event&entityId=x&actor=robot'), 422, 'validation_failed');
        $this->assertProblem($client->get('/api/admin/org/api-alpha/audit-log?page=0'), 422, 'validation_failed');
    }

    public function test_a_foreign_organizer_is_a_404_and_anonymous_a_401(): void
    {
        $alpha = $this->loginAs(ApiFixtures::ALPHA);

        $this->assertProblem($alpha->get('/api/admin/org/api-beta/audit?entityType=event&entityId=x'), 404, 'not_found');
        $this->assertProblem($alpha->get('/api/admin/org/api-beta/audit-log'), 404, 'not_found');
        $this->assertProblem($this->anonymous()->get('/api/admin/org/api-alpha/audit?entityType=event&entityId=x'), 401, 'unauthorized');
        $this->assertProblem($this->anonymous()->get('/api/admin/org/api-alpha/audit-log'), 401, 'unauthorized');
    }

    private function organizerIdOf(ApiClient $client, string $slug): string
    {
        $id = $client->get('/api/admin/org/'.$slug)->json()['id'] ?? null;
        self::assertIsString($id);

        return $id;
    }
}
