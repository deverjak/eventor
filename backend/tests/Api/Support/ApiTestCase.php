<?php

declare(strict_types=1);

namespace Eventor\Tests\Api\Support;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Base of the black-box API suite (`phpunit --testsuite api`, run by bin/api-test). Every test talks real HTTP to
 * the `php-api` stack (own database `eventor_api`, FrankenPHP worker mode, running `worker-api`), the way the Next.js
 * frontend will: cookie session per client, JSON bodies, RFC 9457 errors. There is no kernel and no database here.
 *
 * Each test gets fresh clients (own cookie jar), so tests never share a session.
 */
abstract class ApiTestCase extends TestCase
{
    private static ?string $reachable = null;

    protected static function baseUrl(): string
    {
        $url = $_SERVER['API_BASE_URL'] ?? $_ENV['API_BASE_URL'] ?? getenv('API_BASE_URL');

        return \is_string($url) && '' !== $url ? $url : 'http://php-api';
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (self::$reachable !== static::baseUrl()) {
            try {
                $status = $this->anonymous()->get('/api/health/live')->status;
            } catch (\Throwable $e) {
                self::fail(\sprintf('The API at %s is not reachable (%s). Start it with bin/api-test (or docker compose up -d php-api worker-api).', static::baseUrl(), $e->getMessage()));
            }
            self::assertSame(200, $status, 'The API is up but not healthy.');
            self::$reachable = static::baseUrl();
        }
    }

    /** A client without a session. */
    protected function anonymous(): ApiClient
    {
        return new ApiClient(HttpClient::create(), static::baseUrl());
    }

    /**
     * A client that has signed in through POST /api/auth/login.
     *
     * @param array{email: string, password: string, slug: string, name: string} $account
     */
    protected function loginAs(array $account): ApiClient
    {
        $client = $this->anonymous();
        $response = $client->post('/api/auth/login', ['email' => $account['email'], 'password' => $account['password']]);
        self::assertSame(200, $response->status, 'Login of '.$account['email'].' failed: '.$response->body);
        self::assertTrue($client->hasSession(), 'The login must set the session cookie.');

        return $client;
    }

    /**
     * Asserts an RFC 9457 problem response and returns the decoded body.
     *
     * @return array<array-key, mixed>
     */
    protected function assertProblem(ApiResponse $response, int $status, string $code): array
    {
        self::assertSame($status, $response->status, $response->body);
        self::assertStringStartsWith('application/problem+json', (string) $response->header('Content-Type'));
        $body = $response->json();
        self::assertSame($code, $body['code'] ?? null, $response->body);
        self::assertSame($status, $body['status'] ?? null);
        self::assertIsString($body['title'] ?? null);
        self::assertIsString($body['type'] ?? null);

        return $body;
    }
}
