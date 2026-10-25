<?php

declare(strict_types=1);

namespace Eventor\Tests\Api\Registration;

use Eventor\Tests\Api\Support\ApiFixtures;
use Eventor\Tests\Api\Support\ApiTestCase;

/**
 * GET /api/admin/org/{orgSlug}/events/{eventId}/capacity over real HTTP.
 *
 * The happy path (an event with buckets) is PENDING on Catalog: buckets are created when an event is published and
 * no event can be created through the API yet. It is covered at integration level
 * (CapacityOverviewEndpointTest); add the black-box 200 test here once `PublishEvent` is reachable over HTTP.
 */
final class CapacityApiTest extends ApiTestCase
{
    private const string EVENT = '0199a000-0000-7000-8000-000000000001';

    public function test_anonymous_access_is_a_401_problem(): void
    {
        $response = $this->anonymous()->get('/api/admin/org/'.ApiFixtures::ALPHA['slug'].'/events/'.self::EVENT.'/capacity');

        $this->assertProblem($response, 401, 'unauthorized');
    }

    public function test_an_unknown_event_is_a_404_problem(): void
    {
        $alpha = $this->loginAs(ApiFixtures::ALPHA);

        $response = $alpha->get('/api/admin/org/'.ApiFixtures::ALPHA['slug'].'/events/'.self::EVENT.'/capacity');

        $this->assertProblem($response, 404, 'capacity_bucket_not_found');
    }

    public function test_a_malformed_event_id_is_a_404_problem(): void
    {
        $alpha = $this->loginAs(ApiFixtures::ALPHA);

        $this->assertProblem($alpha->get('/api/admin/org/'.ApiFixtures::ALPHA['slug'].'/events/nope/capacity'), 404, 'capacity_bucket_not_found');
    }

    public function test_a_foreign_organizer_is_a_404_problem_not_a_403(): void
    {
        $alpha = $this->loginAs(ApiFixtures::ALPHA);

        $response = $alpha->get('/api/admin/org/'.ApiFixtures::BETA['slug'].'/events/'.self::EVENT.'/capacity');

        $this->assertProblem($response, 404, 'not_found');
    }
}
