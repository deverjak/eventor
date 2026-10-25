<?php

declare(strict_types=1);

namespace Eventor\Tests\Api\Identity;

use Eventor\Tests\Api\Support\ApiFixtures;
use Eventor\Tests\Api\Support\ApiTestCase;

/**
 * GET /api/admin/org/{orgSlug}: the full chain over HTTP (firewall -> tenant resolver -> voter -> query -> JSON) and
 * the 401 -> 404 -> 403 guard order.
 */
final class OrganizerApiTest extends ApiTestCase
{
    public function test_a_member_reads_their_organizer(): void
    {
        $client = $this->loginAs(ApiFixtures::ALPHA);

        $response = $client->get('/api/admin/org/'.ApiFixtures::ALPHA['slug']);

        self::assertSame(200, $response->status, $response->body);
        self::assertStringStartsWith('application/json', (string) $response->header('Content-Type'));
        $body = $response->json();
        self::assertSame(ApiFixtures::ALPHA['slug'], $body['slug']);
        self::assertSame(ApiFixtures::ALPHA['name'], $body['name']);
        self::assertSame('OWNER', $body['yourRole']);
        self::assertSame(1, $body['memberCount']);
        self::assertIsString($body['id']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[0-9a-f]{4}-[0-9a-f]{12}$/', $body['id'], 'ids are UUIDv7');
        self::assertIsString($body['createdAt']);
        self::assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d[+-]\d\d:\d\d$/', $body['createdAt'], 'times are ISO 8601 with an offset');
    }

    public function test_anonymous_access_is_a_401_problem(): void
    {
        $response = $this->anonymous()->get('/api/admin/org/'.ApiFixtures::ALPHA['slug']);

        $this->assertProblem($response, 401, 'unauthorized');
    }

    public function test_a_foreign_organizer_is_a_404_problem_not_a_403(): void
    {
        $alpha = $this->loginAs(ApiFixtures::ALPHA);

        $response = $alpha->get('/api/admin/org/'.ApiFixtures::BETA['slug']);

        $this->assertProblem($response, 404, 'not_found');
    }

    public function test_a_foreign_organizer_looks_exactly_like_a_missing_one(): void
    {
        $alpha = $this->loginAs(ApiFixtures::ALPHA);

        $foreign = $this->assertProblem($alpha->get('/api/admin/org/'.ApiFixtures::BETA['slug']), 404, 'not_found');
        $missing = $this->assertProblem($alpha->get('/api/admin/org/no-such-organizer'), 404, 'not_found');

        unset($foreign['correlationId'], $missing['correlationId']);
        self::assertSame($foreign, $missing);
    }

    public function test_the_tenant_is_taken_from_the_path_only(): void
    {
        $alpha = $this->loginAs(ApiFixtures::ALPHA);

        $response = $alpha->get('/api/admin/org/'.ApiFixtures::ALPHA['slug'].'?orgSlug='.ApiFixtures::BETA['slug'], ['X-Organizer' => ApiFixtures::BETA['slug']]);

        self::assertSame(200, $response->status);
        self::assertSame(ApiFixtures::ALPHA['slug'], $response->json()['slug']);
    }

    public function test_each_owner_sees_only_their_own_organizer_and_the_tenant_does_not_leak_between_requests(): void
    {
        $alpha = $this->loginAs(ApiFixtures::ALPHA);
        $beta = $this->loginAs(ApiFixtures::BETA);

        // Interleaved requests exercise the FrankenPHP worker: no tenant state may survive a request.
        for ($i = 0; $i < 6; ++$i) {
            self::assertSame(ApiFixtures::ALPHA['slug'], $alpha->get('/api/admin/org/'.ApiFixtures::ALPHA['slug'])->json()['slug']);
            self::assertSame(404, $alpha->get('/api/admin/org/'.ApiFixtures::BETA['slug'])->status);
            self::assertSame(ApiFixtures::BETA['slug'], $beta->get('/api/admin/org/'.ApiFixtures::BETA['slug'])->json()['slug']);
            self::assertSame(404, $beta->get('/api/admin/org/'.ApiFixtures::ALPHA['slug'])->status);
        }
    }

    public function test_the_profile_lists_the_organizer_to_switch_to(): void
    {
        $profile = $this->loginAs(ApiFixtures::BETA)->get('/api/auth/me')->json();

        self::assertIsArray($profile['memberships']);
        self::assertSame([ApiFixtures::BETA['slug']], array_column($profile['memberships'], 'slug'));
    }
}
