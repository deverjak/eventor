<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Shared;

use Eventor\Tests\Support\HttpTestCase;

/**
 * Cross-cutting HTTP behaviour: RFC 9457 problem details, correlation id, CSRF guard, OpenAPI, health.
 */
final class HttpProblemDetailsTest extends HttpTestCase
{
    public function test_unknown_api_route_is_a_problem_not_an_html_page(): void
    {
        $this->request('GET', '/api/no-such-thing');

        $body = $this->assertProblem(404, 'not_found');
        self::assertSame('https://eventor.cz/problems/not-found', $body['type']);
        self::assertArrayNotHasKey('trace', $body);
    }

    public function test_wrong_method_is_a_405_problem(): void
    {
        $this->request('POST', '/api/health/live');

        $this->assertProblem(405, 'method_not_allowed');
    }

    public function test_anonymous_access_to_the_admin_zone_is_a_401_problem(): void
    {
        $this->request('GET', '/api/admin/org/anything');

        $body = $this->assertProblem(401, 'unauthorized');
        self::assertIsString($body['detail']);
        self::assertStringContainsString('/api/auth/login', $body['detail']);
    }

    public function test_every_response_carries_a_correlation_id_and_problems_repeat_it(): void
    {
        $this->request('GET', '/api/admin/org/anything');
        $header = (string) $this->client->getResponse()->headers->get('X-Request-Id');

        self::assertNotSame('', $header);
        $body = $this->assertProblem(401, 'unauthorized');
        self::assertSame($header, $body['correlationId']);
    }

    public function test_a_sane_incoming_request_id_is_adopted(): void
    {
        $this->request('GET', '/api/health/live', headers: ['X-Request-Id' => 'trace-from-frontend-42']);

        self::assertSame('trace-from-frontend-42', $this->client->getResponse()->headers->get('X-Request-Id'));
    }

    public function test_a_hostile_request_id_is_replaced(): void
    {
        $this->request('GET', '/api/health/live', headers: ['X-Request-Id' => "bad id\twith spaces and a very long tail ".str_repeat('x', 100)]);

        $id = (string) $this->client->getResponse()->headers->get('X-Request-Id');
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $id);
    }

    public function test_unsafe_requests_without_a_csrf_token_are_refused(): void
    {
        $this->request('POST', '/api/auth/login', ['email' => 'a@b.cz', 'password' => 'whatever-it-is'], csrf: false);

        $body = $this->assertProblem(403, 'csrf_invalid');
        self::assertIsString($body['detail']);
        self::assertStringContainsString('csrf-token', $body['detail']);
    }

    public function test_a_short_csrf_token_is_refused(): void
    {
        $this->request('POST', '/api/auth/login', ['email' => 'a@b.cz', 'password' => 'whatever-it-is'], headers: ['csrf-token' => 'too-short']);

        $this->assertProblem(403, 'csrf_invalid');
    }

    public function test_a_foreign_origin_is_refused_even_with_a_token(): void
    {
        $this->request('POST', '/api/auth/login', ['email' => 'a@b.cz', 'password' => 'whatever-it-is'], headers: ['Origin' => 'https://evil.example', 'csrf-token' => bin2hex(random_bytes(16))]);

        $this->assertProblem(403, 'csrf_invalid');
    }

    public function test_safe_requests_need_no_csrf_token(): void
    {
        $this->request('GET', '/api/health/live', csrf: false);

        self::assertResponseIsSuccessful();
    }

    public function test_readiness_reports_the_database(): void
    {
        $body = $this->request('GET', '/api/health/ready');

        self::assertResponseIsSuccessful();
        self::assertSame(['status' => 'ok', 'database' => 'up'], $body);
    }

    public function test_the_openapi_document_describes_the_endpoints(): void
    {
        $body = $this->request('GET', '/api/doc.json');

        self::assertResponseIsSuccessful();
        self::assertIsArray($body);
        self::assertIsString($body['openapi'] ?? null);
        self::assertStringStartsWith('3.', $body['openapi']);
        $paths = $body['paths'] ?? [];
        self::assertIsArray($paths);
        foreach (['/api/auth/login', '/api/auth/me', '/api/admin/org/{orgSlug}', '/api/health/live'] as $path) {
            self::assertArrayHasKey($path, $paths);
        }
    }
}
