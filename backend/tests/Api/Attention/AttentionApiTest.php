<?php

declare(strict_types=1);

namespace Eventor\Tests\Api\Attention;

use Eventor\Tests\Api\Support\ApiFixtures;
use Eventor\Tests\Api\Support\ApiTestCase;

/**
 * The "needs attention" queue over real HTTP. Items come from the asynchronous world (dead letters, payment and
 * registration events), which the black-box suite cannot trigger through an endpoint yet, so items are covered in the
 * integration tests (AttentionHttpTest, AttentionSubscribersTest). Here: the contract of the empty queue and errors.
 */
final class AttentionApiTest extends ApiTestCase
{
    private const string BASE = '/api/admin/org/api-alpha/attention';
    private const string UNKNOWN_ITEM = '0199a000-0000-7000-8000-00000000dead';

    public function test_the_queue_of_a_fresh_organizer_is_an_empty_page(): void
    {
        $client = $this->loginAs(ApiFixtures::ALPHA);

        $response = $client->get(self::BASE);

        self::assertSame(200, $response->status, $response->body);
        $body = $response->json();
        self::assertIsArray($body['items']);
        self::assertSame(1, $body['page']);
        self::assertSame(25, $body['perPage']);
        self::assertIsInt($body['total']);
    }

    public function test_the_dashboard_counts_are_available(): void
    {
        $client = $this->loginAs(ApiFixtures::ALPHA);

        $response = $client->get(self::BASE.'/summary');

        self::assertSame(200, $response->status, $response->body);
        $body = $response->json();
        self::assertIsInt($body['unresolved']);
        self::assertArrayHasKey('byType', $body);
    }

    public function test_filters_are_validated(): void
    {
        $client = $this->loginAs(ApiFixtures::ALPHA);

        $this->assertProblem($client->get(self::BASE.'?type=nonsense'), 422, 'validation_failed');
        $this->assertProblem($client->get(self::BASE.'?state=nonsense'), 422, 'validation_failed');
        $this->assertProblem($client->get(self::BASE.'/summary?eventId=nope'), 422, 'validation_failed');
        self::assertSame(200, $client->get(self::BASE.'?type=late_payment&state=open')->status);
    }

    public function test_an_unknown_item_is_a_404_on_read_and_on_action(): void
    {
        $client = $this->loginAs(ApiFixtures::ALPHA);

        $this->assertProblem($client->get(self::BASE.'/'.self::UNKNOWN_ITEM), 404, 'attention_item_not_found');
        $this->assertProblem($client->post(self::BASE.'/'.self::UNKNOWN_ITEM.'/actions/resolve', []), 404, 'attention_item_not_found');
        $this->assertProblem($client->post(self::BASE.'/'.self::UNKNOWN_ITEM.'/actions/acknowledge'), 404, 'attention_item_not_found');
    }

    public function test_a_foreign_organizer_is_a_404(): void
    {
        $client = $this->loginAs(ApiFixtures::ALPHA);
        $beta = '/api/admin/org/'.ApiFixtures::BETA['slug'].'/attention';

        $this->assertProblem($client->get($beta), 404, 'not_found');
        $this->assertProblem($client->get($beta.'/summary'), 404, 'not_found');
        $this->assertProblem($client->get($beta.'/'.self::UNKNOWN_ITEM), 404, 'not_found');
        $this->assertProblem($client->post($beta.'/'.self::UNKNOWN_ITEM.'/actions/resolve', []), 404, 'not_found');
    }

    public function test_anonymous_is_a_401(): void
    {
        $anonymous = $this->anonymous();

        $this->assertProblem($anonymous->get(self::BASE), 401, 'unauthorized');
        $this->assertProblem($anonymous->get(self::BASE.'/summary'), 401, 'unauthorized');
        $this->assertProblem($anonymous->post(self::BASE.'/'.self::UNKNOWN_ITEM.'/actions/resolve', []), 401, 'unauthorized');
    }

    public function test_the_openapi_document_describes_the_queue(): void
    {
        $response = $this->anonymous()->get('/api/doc.json');

        self::assertSame(200, $response->status);
        $paths = $response->json()['paths'] ?? null;
        self::assertIsArray($paths);
        self::assertArrayHasKey('/api/admin/org/{orgSlug}/attention', $paths);
        self::assertArrayHasKey('/api/admin/org/{orgSlug}/attention/{itemId}/actions/{action}', $paths);
    }
}
