<?php

declare(strict_types=1);

namespace Eventor\Tests\Contract\Pairrot;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Phase F0 smoke test of the real Pairrot in the compose stack: it is up, and the two keys written by
 * bin/pairrot-bootstrap (backend/.env.local) work and have the right scope. The adapter contract tests
 * (HttpPairrotGateway, webhook signature) come with the Payments module in phase F3.
 *
 * Skipped when the keys are not configured (run `bin/pairrot-bootstrap` first).
 */
final class PairrotStackSmokeTest extends TestCase
{
    private HttpClientInterface $http;
    private string $baseUrl;
    private string $tenantKey;
    private string $managementKey;

    protected function setUp(): void
    {
        $base = $this->env('PAIRROT_BASE_URL');
        $tenant = $this->env('PAIRROT_TENANT_KEY');
        $management = $this->env('PAIRROT_MANAGEMENT_KEY');
        if ('' === $base || '' === $tenant || '' === $management) {
            self::markTestSkipped('Pairrot keys are not configured. Run bin/pairrot-bootstrap.');
        }

        $this->baseUrl = rtrim($base, '/');
        $this->tenantKey = $tenant;
        $this->managementKey = $management;
        $this->http = HttpClient::create(['timeout' => 10]);
    }

    public function test_requests_without_a_key_are_refused(): void
    {
        $response = $this->http->request('GET', $this->baseUrl.'/v1/tenant');

        self::assertSame(401, $response->getStatusCode());
    }

    public function test_the_tenant_key_reads_the_eventor_root_tenant(): void
    {
        $response = $this->http->request('GET', $this->baseUrl.'/v1/tenant', ['auth_bearer' => $this->tenantKey]);

        self::assertSame(200, $response->getStatusCode());
        $tenant = $response->toArray();
        self::assertSame('active', $tenant['status']);
        self::assertSame('eventor-local', $tenant['external_id']);
        self::assertIsArray($tenant['capabilities']);
        self::assertTrue($tenant['capabilities']['can_create_subtenants'], 'Eventor creates one subtenant per organizer');
    }

    public function test_the_management_key_may_use_management_endpoints_and_the_tenant_key_may_not(): void
    {
        $management = $this->http->request('GET', $this->baseUrl.'/v1/management/attention/summary', ['auth_bearer' => $this->managementKey]);
        self::assertSame(200, $management->getStatusCode());

        $tenant = $this->http->request('GET', $this->baseUrl.'/v1/management/attention/summary', ['auth_bearer' => $this->tenantKey]);
        self::assertSame(403, $tenant->getStatusCode());
    }

    private function env(string $name): string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);
        if (\is_string($value) && '' !== $value) {
            return $value;
        }

        // The test environment does not load .env.local, where bin/pairrot-bootstrap writes the keys.
        $file = \dirname(__DIR__, 3).'/.env.local';
        if (is_file($file)) {
            $local = (new Dotenv())->parse((string) file_get_contents($file));

            $value = $local[$name] ?? '';

            return \is_string($value) ? $value : '';
        }

        return '';
    }
}
