<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Identity;

use Eventor\Identity\Infrastructure\Security\SignUpRateLimitListener;
use Eventor\Tests\Integration\Identity\Support\IdentityHttpTestCase;
use Eventor\Tests\Integration\Identity\Support\Json;
use Symfony\Component\Routing\RouterInterface;

/**
 * ORG-01: POST /api/auth/sign-up.
 */
final class SignUpTest extends IdentityHttpTestCase
{
    private const array BODY = [
        'email' => 'Nova@Example.test',
        'password' => 'correct-horse-battery',
        'displayName' => 'Nova',
        'organizerName' => 'Nový klub',
        'slug' => 'novy-klub',
    ];

    public function test_it_creates_account_organizer_owner_membership_and_policy_and_signs_in(): void
    {
        $body = $this->request('POST', '/api/auth/sign-up', self::BODY);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('nova@example.test', Json::at($body, 'email'));
        self::assertSame('novy-klub', Json::at($body, 'memberships', 0, 'slug'));
        self::assertSame('OWNER', Json::at($body, 'memberships', 0, 'role'));
        $this->request('GET', '/api/auth/me');
        self::assertResponseIsSuccessful('the owner is signed in');

        self::assertSame(1, $this->dbInt("SELECT count(*) FROM identity.organizer WHERE slug = 'novy-klub' AND contact_email = 'nova@example.test'"));
        self::assertSame(1, $this->dbInt('SELECT count(*) FROM identity.organizer_policy'));
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM audit.audit_entry WHERE action = 'organizer.registered'"));
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM audit.audit_entry WHERE action = 'membership.granted'"));
        self::assertStringNotContainsString('correct-horse', $this->dbString('SELECT password_hash FROM identity.user_account'));
    }

    public function test_the_new_owner_is_signed_in_and_reaches_the_admin_zone(): void
    {
        $this->request('POST', '/api/auth/sign-up', self::BODY);

        $me = $this->request('GET', '/api/auth/me');
        self::assertResponseIsSuccessful();
        self::assertSame('nova@example.test', Json::at($me, 'email'));

        $org = $this->request('GET', '/api/admin/org/novy-klub');
        self::assertResponseIsSuccessful();
        self::assertSame('OWNER', Json::at($org, 'yourRole'));
    }

    public function test_the_new_owner_can_log_in_again_with_the_chosen_password(): void
    {
        $this->request('POST', '/api/auth/sign-up', self::BODY);
        $this->logout();

        $this->login('nova@example.test');
        $this->request('GET', '/api/auth/me');
        self::assertResponseIsSuccessful();
    }

    public function test_an_existing_email_is_a_409_and_changes_nothing(): void
    {
        $this->createOwner('nova@example.test', 'prvni-klub');

        $this->request('POST', '/api/auth/sign-up', self::BODY);

        $this->assertProblem(409, 'email_already_registered');
        self::assertSame(1, $this->dbInt('SELECT count(*) FROM identity.organizer'));
    }

    public function test_a_taken_slug_is_a_409(): void
    {
        $this->createOwner('jana@example.test', 'novy-klub');

        $this->request('POST', '/api/auth/sign-up', self::BODY);

        $this->assertProblem(409, 'organizer_slug_taken');
        self::assertSame(0, $this->dbInt("SELECT count(*) FROM identity.user_account WHERE email = 'nova@example.test'"));
    }

    public function test_invalid_input_is_a_422_with_violations(): void
    {
        $this->request('POST', '/api/auth/sign-up', ['email' => 'nope', 'password' => 'short', 'displayName' => '', 'organizerName' => '', 'slug' => 'Bad Slug']);

        $problem = $this->assertProblem(422, 'validation_failed');
        $paths = array_column(Json::rows($problem, 'violations'), 'path');
        foreach (['email', 'password', 'displayName', 'organizerName', 'slug'] as $path) {
            self::assertContains($path, $paths);
        }
        self::assertSame(0, $this->dbInt('SELECT count(*) FROM identity.user_account'));
    }

    public function test_the_csrf_header_is_required(): void
    {
        $this->request('POST', '/api/auth/sign-up', self::BODY, csrf: false);

        $this->assertProblem(403, 'csrf_invalid');
    }

    public function test_the_rate_limited_routes_exist(): void
    {
        // The limiter itself is exercised in SignUpRateLimitListenerTest (unit; the array cache of the test kernel is
        // reset between requests). Here only the wiring: every route the listener throttles really exists.
        $router = static::getContainer()->get('router');
        \assert($router instanceof RouterInterface);

        foreach (SignUpRateLimitListener::ROUTES as $name) {
            self::assertNotNull($router->getRouteCollection()->get($name), $name);
        }
    }
}
