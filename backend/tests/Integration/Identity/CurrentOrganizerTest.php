<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Identity;

use Eventor\Identity\Domain\Account\UserAccountId;
use Eventor\Identity\Domain\Membership\Role;
use Eventor\Shared\Application\Tenant\TenantContext;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Tests\Support\Foundry\MembershipFactory;
use Eventor\Tests\Support\HttpTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * The admin zone guard order (ADR 005): 401 (no session) -> 404 (unknown organizer or no membership) -> 403 (role).
 */
final class CurrentOrganizerTest extends HttpTestCase
{
    use Factories;

    public function test_anonymous_gets_401_before_anything_else(): void
    {
        $this->createOwner('jana@example.test', 'klub');

        $this->request('GET', '/api/admin/org/klub');

        $this->assertProblem(401, 'unauthorized');
    }

    public function test_a_member_sees_their_organizer(): void
    {
        $ids = $this->createOwner('jana@example.test', 'klub', 'Klub Plzeň');
        $this->login('jana@example.test');

        $body = $this->request('GET', '/api/admin/org/klub');

        self::assertResponseIsSuccessful();
        self::assertIsArray($body);
        self::assertSame($ids['organizerId'], $body['id']);
        self::assertSame('klub', $body['slug']);
        self::assertSame('Klub Plzeň', $body['name']);
        self::assertSame(1, $body['memberCount']);
        self::assertSame('OWNER', $body['yourRole']);
    }

    public function test_an_unknown_organizer_is_404(): void
    {
        $this->createOwner('jana@example.test', 'klub');
        $this->login('jana@example.test');

        $this->request('GET', '/api/admin/org/does-not-exist');

        $this->assertProblem(404, 'not_found');
    }

    public function test_a_foreign_organizer_is_404_not_403_and_looks_exactly_like_a_missing_one(): void
    {
        $this->createOwner('jana@example.test', 'klub');
        $this->createOwner('petr@example.test', 'cizi-klub');
        $this->login('jana@example.test');

        $this->request('GET', '/api/admin/org/cizi-klub');
        $foreign = $this->assertProblem(404, 'not_found');

        $this->request('GET', '/api/admin/org/neexistuje');
        $missing = $this->assertProblem(404, 'not_found');

        unset($foreign['correlationId'], $missing['correlationId']);
        self::assertSame($foreign, $missing, 'a foreign tenant must be indistinguishable from a missing one');
    }

    public function test_a_member_without_the_permission_gets_403(): void
    {
        $owner = $this->createOwner('jana@example.test', 'klub');
        $finance = $this->createOwner('fin@example.test', 'fin-own-club');
        MembershipFactory::createOne([
            'userAccountId' => UserAccountId::fromString($finance['userAccountId']),
            'organizerId' => OrganizerId::fromString($owner['organizerId']),
            'role' => Role::Finance,
        ]);

        $this->login('fin@example.test');

        // Finance may see the organizer (organizer.view) ...
        $this->request('GET', '/api/admin/org/klub');
        self::assertResponseIsSuccessful();

        // ... but not manage the team (test-only endpoint, permission team.manage).
        $this->request('GET', '/api/admin/org/klub/_probe/team');
        $body = $this->assertProblem(403, 'forbidden');
        self::assertSame('Nedostatečné oprávnění', $body['title']);
    }

    public function test_the_owner_passes_the_permission_check(): void
    {
        $this->createOwner('jana@example.test', 'klub');
        $this->login('jana@example.test');

        $body = $this->request('GET', '/api/admin/org/klub/_probe/team');

        self::assertResponseIsSuccessful();
        self::assertSame(['organizer' => 'klub', 'ok' => true], $body);
    }

    public function test403_for_a_foreign_organizer_never_happens404_comes_first(): void
    {
        $this->createOwner('jana@example.test', 'klub');
        $this->createOwner('petr@example.test', 'cizi-klub');
        $this->login('jana@example.test');

        $this->request('POST', '/api/admin/org/cizi-klub/_probe/team', []);

        $this->assertProblem(404, 'not_found');
    }

    public function test_the_tenant_does_not_leak_into_the_next_request(): void
    {
        $this->createOwner('jana@example.test', 'klub');
        $this->login('jana@example.test');
        $this->request('GET', '/api/admin/org/klub');
        self::assertResponseIsSuccessful();

        // A fresh request outside any organizer URL runs with no active tenant.
        $this->request('GET', '/api/auth/me');
        self::assertResponseIsSuccessful();

        self::assertNull($this->service(TenantContext::class)->current());
    }
}
