<?php

declare(strict_types=1);

namespace Eventor\Tests\Api\Shared;

use Eventor\Tests\Api\Support\ApiTestCase;

/**
 * Cross-cutting behaviour that every client relies on: health, correlation ids, problem details, OpenAPI.
 */
final class PlatformApiTest extends ApiTestCase
{
    public function test_the_api_is_live_and_ready(): void
    {
        self::assertSame(['status' => 'ok'], $this->anonymous()->get('/api/health/live')->json());
        self::assertSame(['status' => 'ok', 'database' => 'up'], $this->anonymous()->get('/api/health/ready')->json());
    }

    public function test_every_response_has_a_correlation_id_and_an_incoming_one_is_kept(): void
    {
        $generated = $this->anonymous()->get('/api/health/live');
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', (string) $generated->header('X-Request-Id'));

        $kept = $this->anonymous()->get('/api/health/live', ['X-Request-Id' => 'frontend-trace-0001']);
        self::assertSame('frontend-trace-0001', $kept->header('X-Request-Id'));
    }

    public function test_errors_are_problem_details_with_the_correlation_id(): void
    {
        $response = $this->anonymous()->get('/api/admin/org/whatever', ['X-Request-Id' => 'frontend-trace-0002']);

        $body = $this->assertProblem($response, 401, 'unauthorized');
        self::assertSame('frontend-trace-0002', $body['correlationId']);
        self::assertSame('https://eventor.cz/problems/unauthorized', $body['type']);
    }

    public function test_an_unknown_route_is_a_404_problem(): void
    {
        $this->assertProblem($this->anonymous()->get('/api/this-does-not-exist'), 404, 'not_found');
    }

    public function test_a_method_the_route_does_not_allow_is_a_405_problem(): void
    {
        $this->assertProblem($this->anonymous()->post('/api/health/live'), 405, 'method_not_allowed');
    }

    public function test_the_openapi_document_is_published_for_the_typescript_client(): void
    {
        $response = $this->anonymous()->get('/api/doc.json');

        self::assertSame(200, $response->status);
        $document = $response->json();
        self::assertIsString($document['openapi']);
        self::assertStringStartsWith('3.', $document['openapi']);
        self::assertIsArray($document['paths']);
        foreach (['/api/auth/login', '/api/auth/me', '/api/admin/org/{orgSlug}', '/api/health/live', '/api/health/ready'] as $path) {
            self::assertArrayHasKey($path, $document['paths'], "$path must be documented");
        }
    }

    public function test_no_stack_trace_or_internal_detail_ever_leaks(): void
    {
        $response = $this->anonymous()->get('/api/admin/org/'.str_repeat('x', 5000));

        self::assertStringNotContainsString('vendor/', $response->body);
        self::assertStringNotContainsString('Stack trace', $response->body);
    }
}
