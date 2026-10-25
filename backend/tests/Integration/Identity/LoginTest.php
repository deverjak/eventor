<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Identity;

use Eventor\Tests\Support\HttpTestCase;

final class LoginTest extends HttpTestCase
{
    public function test_a_member_signs_in_and_gets_the_profile_with_memberships(): void
    {
        $ids = $this->createOwner('jana@example.test', 'klub-plzen', 'Klub Plzeň');

        $body = $this->request('POST', '/api/auth/login', ['email' => 'jana@example.test', 'password' => self::PASSWORD]);

        self::assertResponseStatusCodeSame(200);
        self::assertIsArray($body);
        self::assertSame($ids['userAccountId'], $body['id']);
        self::assertSame('jana@example.test', $body['email']);
        self::assertSame([['organizerId' => $ids['organizerId'], 'slug' => 'klub-plzen', 'name' => 'Klub Plzeň', 'role' => 'OWNER']], $body['memberships']);
        self::assertArrayNotHasKey('password', $body);
        self::assertArrayNotHasKey('passwordHash', $body);
    }

    public function test_the_e_mail_is_case_insensitive(): void
    {
        $this->createOwner('jana@example.test', 'klub');

        $this->request('POST', '/api/auth/login', ['email' => 'JANA@Example.TEST', 'password' => self::PASSWORD]);

        self::assertResponseStatusCodeSame(200);
    }

    public function test_me_returns_the_same_profile_after_login(): void
    {
        $this->createOwner('jana@example.test', 'klub');
        $this->login('jana@example.test');

        $me = $this->request('GET', '/api/auth/me');

        self::assertResponseIsSuccessful();
        self::assertIsArray($me);
        self::assertSame('jana@example.test', $me['email']);
    }

    public function test_a_wrong_password_and_an_unknown_account_get_the_same_answer(): void
    {
        $this->createOwner('jana@example.test', 'klub');

        $this->request('POST', '/api/auth/login', ['email' => 'jana@example.test', 'password' => 'definitely-not-it']);
        $wrongPassword = $this->assertProblem(401, 'invalid_credentials');

        $this->request('POST', '/api/auth/login', ['email' => 'nobody@example.test', 'password' => 'definitely-not-it']);
        $unknownAccount = $this->assertProblem(401, 'invalid_credentials');

        unset($wrongPassword['correlationId'], $unknownAccount['correlationId']);
        self::assertSame($wrongPassword, $unknownAccount, 'the response must not reveal whether the account exists');
    }

    public function test_me_requires_a_session(): void
    {
        $this->request('GET', '/api/auth/me');

        $this->assertProblem(401, 'unauthorized');
    }

    public function test_logout_ends_the_session(): void
    {
        $this->createOwner('jana@example.test', 'klub');
        $this->login('jana@example.test');

        $this->request('POST', '/api/auth/logout');
        self::assertResponseStatusCodeSame(204);

        $this->request('GET', '/api/auth/me');
        $this->assertProblem(401, 'unauthorized');
    }

    public function test_a_disabled_account_cannot_sign_in(): void
    {
        $ids = $this->createOwner('jana@example.test', 'klub');
        $this->connection()->executeStatement('UPDATE identity.user_account SET disabled_at = now() WHERE id = ?', [$ids['userAccountId']]);
        $this->forgetLoadedEntities();

        $this->request('POST', '/api/auth/login', ['email' => 'jana@example.test', 'password' => self::PASSWORD]);

        $this->assertProblem(401, 'invalid_credentials');
    }

    public function test_a_session_dies_when_the_account_is_disabled(): void
    {
        $ids = $this->createOwner('jana@example.test', 'klub');
        $this->login('jana@example.test');
        $this->connection()->executeStatement('UPDATE identity.user_account SET disabled_at = now() WHERE id = ?', [$ids['userAccountId']]);

        $this->request('GET', '/api/auth/me');

        $this->assertProblem(401, 'unauthorized');
    }

    public function test_passwords_are_stored_as_argon_or_bcrypt_hashes_never_in_clear(): void
    {
        $this->createOwner('jana@example.test', 'klub');

        $hash = $this->dbString("SELECT password_hash FROM identity.user_account WHERE email = 'jana@example.test'");

        self::assertNotSame(self::PASSWORD, $hash);
        self::assertMatchesRegularExpression('/^\$(argon2id|2y)\$/', $hash);
    }
}
