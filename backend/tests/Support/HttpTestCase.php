<?php

declare(strict_types=1);

namespace Eventor\Tests\Support;

use Doctrine\DBAL\Connection;
use Eventor\Shared\Application\Messaging\CommandBus;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Base of HTTP integration tests (status codes, problem details, 401/403/404, tenancy) against the in-process
 * kernel. The browser keeps cookies, so `login()` gives a real session; unsafe requests carry a fresh `csrf-token`
 * header like a real client (pass `csrf: false` to test the guard).
 */
abstract class HttpTestCase extends WebTestCase
{
    use ClockSensitiveTrait;
    use CreatesOwners;
    use FetchesServices;
    use ReadsDatabase;

    public const string PASSWORD = 'correct-horse-battery';

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
    }

    /**
     * @param array<string, mixed>|null $json
     * @param array<string, string>     $headers
     *
     * @return array<array-key, mixed>|null the decoded JSON body (null for an empty body)
     */
    protected function request(string $method, string $uri, ?array $json = null, array $headers = [], bool $csrf = true): ?array
    {
        $server = ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'];
        if ($csrf && !\in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            $server['HTTP_CSRF_TOKEN'] = bin2hex(random_bytes(16));
        }
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        $this->client->request($method, $uri, server: $server, content: null === $json ? null : json_encode($json, \JSON_THROW_ON_ERROR));

        $content = $this->client->getResponse()->getContent();
        if (false === $content || '' === $content) {
            return null;
        }

        /** @var array<array-key, mixed> $decoded */
        $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        return $decoded;
    }

    protected function login(string $email, string $password = self::PASSWORD): void
    {
        $this->request('POST', '/api/auth/login', ['email' => $email, 'password' => $password]);
        self::assertResponseStatusCodeSame(200, 'Login failed.');
    }

    /**
     * Asserts an RFC 9457 problem response and returns its body.
     *
     * @return array<string, mixed>
     */
    protected function assertProblem(int $status, string $code): array
    {
        $response = $this->client->getResponse();
        self::assertSame($status, $response->getStatusCode(), (string) $response->getContent());
        self::assertStringStartsWith('application/problem+json', (string) $response->headers->get('Content-Type'));

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame($code, $body['code'] ?? null, 'Problem code mismatch: '.$response->getContent());
        self::assertSame($status, $body['status'] ?? null);
        self::assertArrayHasKey('title', $body);
        self::assertArrayHasKey('type', $body);

        return $body;
    }

    /**
     * The first request of a test runs on the kernel that already served the fixtures, so entities loaded while
     * arranging are still in its identity map. Call this after changing rows with raw SQL.
     */
    protected function forgetLoadedEntities(): void
    {
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        \assert($em instanceof \Doctrine\ORM\EntityManagerInterface);
        $em->clear();
    }

    protected function connection(): Connection
    {
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');
        \assert($connection instanceof Connection);

        return $connection;
    }

    protected function commandBus(): CommandBus
    {
        $bus = static::getContainer()->get('test.command_bus');
        \assert($bus instanceof CommandBus);

        return $bus;
    }
}
