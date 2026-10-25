<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Identity;

use Eventor\Tests\Integration\Identity\Support\IdentityHttpTestCase;

/**
 * ADR 006 / chapter 08: no team session lives longer than 7 days from the sign-in, however active it is.
 */
final class SessionLimitTest extends IdentityHttpTestCase
{
    public function test_a_session_survives_six_days_of_activity_and_ends_after_seven(): void
    {
        $this->createOwner('jana@example.test', 'klub');
        self::mockTime('2026-11-02 08:00:00');
        $this->login('jana@example.test');

        foreach (['2026-11-03 08:00:00', '2026-11-05 08:00:00', '2026-11-08 07:59:00'] as $moment) {
            self::mockTime($moment);
            $this->request('GET', '/api/auth/me');
            self::assertResponseIsSuccessful("still signed in at $moment");
        }

        self::mockTime('2026-11-09 08:00:01');
        $this->request('GET', '/api/auth/me');
        $this->assertProblem(401, 'unauthorized');

        // The old cookie is dead for good; signing in again starts a new 7 days.
        $this->request('GET', '/api/admin/org/klub');
        $this->assertProblem(401, 'unauthorized');
        $this->login('jana@example.test');
        $this->request('GET', '/api/auth/me');
        self::assertResponseIsSuccessful();
    }

    public function test_sign_up_starts_the_clock_too(): void
    {
        self::mockTime('2026-11-02 08:00:00');
        $this->request('POST', '/api/auth/sign-up', ['email' => 'nova@example.test', 'password' => 'correct-horse-battery', 'displayName' => 'Nova', 'organizerName' => 'Nový', 'slug' => 'novy']);
        self::assertResponseStatusCodeSame(201);

        self::mockTime('2026-11-10 08:00:00');
        $this->request('GET', '/api/auth/me');

        $this->assertProblem(401, 'unauthorized');
    }
}
