<?php

declare(strict_types=1);

namespace Eventor\Tests\Api\Identity;

use Eventor\Tests\Api\Support\ApiFixtures;
use Eventor\Tests\Api\Support\ApiTestCase;

/**
 * POST /api/auth/login, GET /api/auth/me, POST /api/auth/logout over real HTTP.
 */
final class AuthApiTest extends ApiTestCase
{
    public function test_a_team_member_signs_in_and_receives_a_profile_and_a_session_cookie(): void
    {
        $client = $this->anonymous();

        $response = $client->post('/api/auth/login', ['email' => ApiFixtures::ALPHA['email'], 'password' => ApiFixtures::ALPHA['password']]);

        self::assertSame(200, $response->status, $response->body);
        $profile = $response->json();
        self::assertSame(ApiFixtures::ALPHA['email'], $profile['email']);
        $memberships = $profile['memberships'] ?? null;
        self::assertIsArray($memberships);
        self::assertCount(1, $memberships);
        self::assertIsArray($memberships[0]);
        self::assertSame(ApiFixtures::ALPHA['slug'], $memberships[0]['slug']);
        self::assertSame('OWNER', $memberships[0]['role']);
        self::assertArrayNotHasKey('password', $profile);

        $cookie = implode("\n", $response->headerLines('Set-Cookie'));
        self::assertStringContainsString('EVENTOR_SESSID=', $cookie);
        self::assertStringContainsString('httponly', strtolower($cookie), 'the session cookie must be HttpOnly');
        self::assertStringContainsString('samesite=lax', strtolower($cookie), 'the session cookie must be SameSite=Lax');
    }

    public function test_me_returns_the_signed_in_member(): void
    {
        $client = $this->loginAs(ApiFixtures::ALPHA);

        $response = $client->get('/api/auth/me');

        self::assertSame(200, $response->status);
        self::assertSame(ApiFixtures::ALPHA['email'], $response->json()['email']);
    }

    public function test_me_without_a_session_is_a_401_problem(): void
    {
        $response = $this->anonymous()->get('/api/auth/me');

        $this->assertProblem($response, 401, 'unauthorized');
    }

    public function test_a_wrong_password_is_a_401_problem(): void
    {
        $response = $this->anonymous()->post('/api/auth/login', ['email' => ApiFixtures::ALPHA['email'], 'password' => 'wrong-password-123']);

        $body = $this->assertProblem($response, 401, 'invalid_credentials');
        self::assertArrayNotHasKey('trace', $body);
    }

    public function test_an_unknown_account_gets_exactly_the_same_answer_as_a_wrong_password(): void
    {
        $wrongPassword = $this->assertProblem(
            $this->anonymous()->post('/api/auth/login', ['email' => ApiFixtures::ALPHA['email'], 'password' => 'wrong-password-123']),
            401,
            'invalid_credentials',
        );
        $unknown = $this->assertProblem(
            $this->anonymous()->post('/api/auth/login', ['email' => 'nobody@api-test.example', 'password' => 'wrong-password-123']),
            401,
            'invalid_credentials',
        );

        unset($wrongPassword['correlationId'], $unknown['correlationId']);
        self::assertSame($wrongPassword, $unknown);
    }

    public function test_login_without_the_csrf_token_is_refused(): void
    {
        $response = $this->anonymous()->post('/api/auth/login', ['email' => ApiFixtures::ALPHA['email'], 'password' => ApiFixtures::ALPHA['password']], csrf: false);

        $this->assertProblem($response, 403, 'csrf_invalid');
    }

    public function test_logout_ends_the_session(): void
    {
        $client = $this->loginAs(ApiFixtures::ALPHA);

        $logout = $client->post('/api/auth/logout');
        self::assertSame(204, $logout->status);

        $this->assertProblem($client->get('/api/auth/me'), 401, 'unauthorized');
    }

    public function test_two_clients_have_independent_sessions(): void
    {
        $alpha = $this->loginAs(ApiFixtures::ALPHA);
        $beta = $this->loginAs(ApiFixtures::BETA);

        self::assertSame(ApiFixtures::ALPHA['email'], $alpha->get('/api/auth/me')->json()['email']);
        self::assertSame(ApiFixtures::BETA['email'], $beta->get('/api/auth/me')->json()['email']);

        $alpha->post('/api/auth/logout');
        self::assertSame(200, $beta->get('/api/auth/me')->status, "alpha's logout must not end beta's session");
    }
}
