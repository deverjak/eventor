<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Architecture;

use Eventor\Identity\Contract\Attribute\CurrentOrganizer;
use Eventor\Tests\Support\HttpTestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * ADR 005, generic over every module: for each admin route known to the router, a member of organizer A who asks for
 * organizer B's URL gets 404 (not 403, not data). Later modules are covered automatically: their routes appear in
 * the router and must start with #[CurrentOrganizer].
 */
final class TenantIsolationTest extends HttpTestCase
{
    private const string PREFIX = '/api/admin/org/{orgSlug}';

    public function test_every_admin_route_answers_404_for_a_foreign_organizer(): void
    {
        $this->createOwner('alice@example.test', 'iso-a');
        $this->createOwner('bob@example.test', 'iso-b');
        $this->login('alice@example.test');

        $router = static::getContainer()->get('router');
        \assert($router instanceof RouterInterface);

        $tested = [];
        foreach ($router->getRouteCollection() as $name => $route) {
            if (!str_starts_with($route->getPath(), self::PREFIX)) {
                continue;
            }

            $methods = [] === $route->getMethods() ? ['GET'] : $route->getMethods();
            foreach ($methods as $method) {
                $this->request($method, $this->urlFor($route->getPath(), 'iso-b', $route->getRequirements()), 'GET' === $method ? null : []);

                $body = $this->assertProblem(404, 'not_found');
                self::assertSame(
                    $name,
                    $this->client->getRequest()->attributes->get('_route'),
                    "$method {$route->getPath()}: the 404 must come from the tenant guard of this route, not from a routing miss.",
                );
                self::assertSame('Organizer not found.', $body['detail']);
                $tested[] = "$method $name";
            }
        }

        self::assertContains('GET api_admin_organizer_detail', $tested, 'The organizer detail route must be covered.');
        self::assertGreaterThanOrEqual(3, \count($tested));
    }

    public function test_every_admin_route_resolves_its_tenant_first(): void
    {
        $router = static::getContainer()->get('router');
        \assert($router instanceof RouterInterface);

        $checked = 0;
        foreach ($router->getRouteCollection() as $name => $route) {
            if (!str_starts_with($route->getPath(), self::PREFIX)) {
                continue;
            }

            $default = $route->getDefault('_controller');
            self::assertIsString($default);
            $controller = $default;
            [$class, $method] = str_contains($controller, '::') ? explode('::', $controller, 2) : [$controller, '__invoke'];
            self::assertTrue(class_exists($class), "$name: controller $controller");

            $hasResolver = false;
            foreach ((new \ReflectionMethod($class, $method))->getParameters() as $parameter) {
                $hasResolver = $hasResolver || [] !== $parameter->getAttributes(CurrentOrganizer::class);
            }
            self::assertTrue($hasResolver, "$name ($controller) is an admin route and must take a #[CurrentOrganizer] argument: the tenant comes from the URL.");
            ++$checked;
        }

        self::assertGreaterThanOrEqual(3, $checked);
    }

    public function test_a_member_of_a_organizer_is_not_enough_to_read_a_neighbours_data_with_the_right_slug_in_a_query(): void
    {
        $this->createOwner('alice@example.test', 'iso-a');
        $this->createOwner('bob@example.test', 'iso-b');
        $this->login('alice@example.test');

        // The tenant comes from the path only; a query parameter or header cannot select another one.
        $body = $this->request('GET', '/api/admin/org/iso-a?orgSlug=iso-b', headers: ['X-Organizer' => 'iso-b']);

        self::assertResponseIsSuccessful();
        self::assertIsArray($body);
        self::assertSame('iso-a', $body['slug']);
    }

    /**
     * @param array<mixed>          $requirements a placeholder restricted to plain alternatives (`open|close`) gets the
     *                                            first one, a numeric one (`\d+`) gets 1
     */
    private function urlFor(string $path, string $orgSlug, array $requirements = []): string
    {
        $path = str_replace('{orgSlug}', $orgSlug, $path);

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
