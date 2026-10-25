<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Architecture;

use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Domain\Membership\Role;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\UserAccountId;
use Eventor\Tests\Support\Foundry\MembershipFactory;
use Eventor\Tests\Support\HttpTestCase;
use Symfony\Component\Routing\RouterInterface;
use Zenstruck\Foundry\Test\Factories;

/**
 * ORG-03, generic over every module: a member restricted to event A gets 403 for any admin GET route of another event
 * and for organizer-wide routes, unless the route declares `#[RequiresPermission(scopedAllowed: true)]`. Later
 * modules are covered automatically through the router.
 */
final class EventScopeTest extends HttpTestCase
{
    use Factories;

    private const string PREFIX = '/api/admin/org/{orgSlug}';
    private const string SCOPED = 'scoped@example.test';

    private string $eventA;
    private string $eventB;

    protected function setUp(): void
    {
        parent::setUp();
        $owner = $this->createOwner('owner@example.test', 'scope-club');
        $this->login('owner@example.test');
        $this->eventA = $this->createEvent('Akce A', 'akce-a');
        $this->eventB = $this->createEvent('Akce B', 'akce-b');

        $account = $this->createOwner(self::SCOPED, 'scoped-own');
        $membership = MembershipFactory::createOne([
            'userAccountId' => UserAccountId::fromString($account['userAccountId']),
            'organizerId' => OrganizerId::fromString($owner['organizerId']),
            'role' => Role::EventManager,
        ]);
        $this->connection()->executeStatement(
            'INSERT INTO identity.membership_event_scope (membership_id, event_id, organizer_id) VALUES (?, ?, ?)',
            [$membership->id()->toString(), $this->eventA, $owner['organizerId']],
        );

        $this->client->getCookieJar()->clear();
        $this->login(self::SCOPED);
    }

    public function test_a_scoped_member_works_on_its_own_event_and_sees_only_it(): void
    {
        $detail = $this->request('GET', "/api/admin/org/scope-club/events/{$this->eventA}");
        self::assertResponseIsSuccessful();
        self::assertIsArray($detail);

        $list = $this->request('GET', '/api/admin/org/scope-club/events');
        self::assertResponseIsSuccessful();
        self::assertIsArray($list);
        self::assertIsArray($list['items']);
        self::assertSame([$this->eventA], array_column($list['items'], 'id'));
    }

    public function test_a_scoped_member_cannot_change_another_event(): void
    {
        $this->request('POST', "/api/admin/org/scope-club/events/{$this->eventB}/publish");
        $this->assertProblem(403, 'forbidden');

        $this->request('POST', '/api/admin/org/scope-club/events', ['details' => ['name' => 'X'], 'mode' => 'simple']);
        self::assertContains($this->client->getResponse()->getStatusCode(), [403, 422], 'creating events is organizer-wide');
    }

    public function test_every_admin_get_route_honours_the_event_scope(): void
    {
        $router = static::getContainer()->get('router');
        \assert($router instanceof RouterInterface);

        $checked = [];
        foreach ($router->getRouteCollection() as $name => $route) {
            $path = $route->getPath();
            $methods = [] === $route->getMethods() ? ['GET'] : $route->getMethods();
            if (!str_starts_with($path, self::PREFIX) || !\in_array('GET', $methods, true)) {
                continue;
            }
            $hasEvent = str_contains($path, '{eventId}');
            if (!$hasEvent && $this->scopedAllowed($route->getDefault('_controller'))) {
                continue;
            }

            $this->request('GET', $this->urlFor($path, $route->getRequirements()));
            self::assertSame(403, $this->client->getResponse()->getStatusCode(), \sprintf(
                'GET %s (%s) must refuse a member scoped to another event: %s',
                $path,
                $name,
                (string) $this->client->getResponse()->getContent(),
            ));
            $checked[] = $name;
        }

        self::assertContains('api_admin_event_detail', $checked);
        self::assertGreaterThanOrEqual(5, \count($checked));
    }

    private function createEvent(string $name, string $slug): string
    {
        $created = $this->request('POST', '/api/admin/org/scope-club/events', ['mode' => 'simple', 'slug' => $slug, 'details' => [
            'name' => $name,
            'startsAt' => '2026-11-21T08:00:00+01:00',
            'endsAt' => '2026-11-22T16:00:00+01:00',
            'venues' => [['name' => 'Park', 'address' => 'Plzeň']],
        ]]);
        self::assertResponseStatusCodeSame(201, (string) $this->client->getResponse()->getContent());
        self::assertIsArray($created);
        self::assertIsString($created['eventId']);

        return $created['eventId'];
    }

    private function scopedAllowed(mixed $controller): bool
    {
        if (!\is_string($controller)) {
            return false;
        }
        [$class, $method] = str_contains($controller, '::') ? explode('::', $controller, 2) : [$controller, '__invoke'];
        if (!class_exists($class) || !method_exists($class, $method)) {
            return false;
        }
        foreach ((new \ReflectionMethod($class, $method))->getAttributes(RequiresPermission::class) as $attribute) {
            if ($attribute->newInstance()->scopedAllowed) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<mixed> $requirements
     */
    private function urlFor(string $path, array $requirements): string
    {
        $path = str_replace(['{orgSlug}', '{eventId}'], ['scope-club', $this->eventB], $path);

        return (string) preg_replace_callback('/\{(\w+)\}/', static function (array $m) use ($requirements): string {
            $requirement = \is_string($requirements[$m[1]] ?? null) ? $requirements[$m[1]] : null;
            if (null !== $requirement && 1 === preg_match('/^\w+(\|\w+)*$/', $requirement)) {
                return explode('|', $requirement)[0];
            }
            if (null !== $requirement && 1 === preg_match('{^'.$requirement.'$}', '1')) {
                return '1';
            }

            return str_ends_with(strtolower($m[1]), 'id') ? '0199a000-0000-7000-8000-000000000000' : 'x';
        }, $path);
    }
}
