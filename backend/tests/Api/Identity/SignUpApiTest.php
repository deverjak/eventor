<?php

declare(strict_types=1);

namespace Eventor\Tests\Api\Identity;

use Eventor\Tests\Api\Identity\Support\IdentityApiTestCase;
use Eventor\Tests\Integration\Identity\Support\Json;

/**
 * ORG-01 over real HTTP: sign-up -> (signed in) -> me -> log out -> login again.
 */
final class SignUpApiTest extends IdentityApiTestCase
{
    public function test_sign_up_then_me_then_login_again(): void
    {
        $slug = self::unique('club');
        $email = $slug.'@api-test.example';
        $client = $this->anonymous();

        $response = $client->post('/api/auth/sign-up', ['email' => $email, 'password' => self::PASSWORD, 'displayName' => 'Jana', 'organizerName' => 'Klub', 'slug' => $slug]);

        self::assertSame(201, $response->status, $response->body);
        self::assertTrue($client->hasSession(), 'sign-up signs the owner in');
        $profile = $response->json();
        self::assertSame($email, Json::at($profile, 'email'));
        self::assertSame($slug, Json::at($profile, 'memberships', 0, 'slug'));
        self::assertSame('OWNER', Json::at($profile, 'memberships', 0, 'role'));

        $me = $client->get('/api/auth/me');
        self::assertSame(200, $me->status);
        self::assertSame($email, Json::at($me->json(), 'email'));

        $org = $client->get("/api/admin/org/$slug");
        self::assertSame(200, $org->status);
        self::assertSame('OWNER', Json::at($org->json(), 'yourRole'));

        self::assertSame(204, $client->post('/api/auth/logout')->status);
        $again = $this->anonymous();
        self::assertSame(200, $again->post('/api/auth/login', ['email' => $email, 'password' => self::PASSWORD])->status);
        self::assertSame($email, Json::at($again->get('/api/auth/me')->json(), 'email'));
    }

    public function test_a_registered_email_or_taken_slug_is_a_409_problem(): void
    {
        $org = $this->signUp('dup');

        $sameEmail = $this->anonymous()->post('/api/auth/sign-up', ['email' => $org['email'], 'password' => self::PASSWORD, 'displayName' => 'X', 'organizerName' => 'X', 'slug' => self::unique('other')]);
        $this->assertProblem($sameEmail, 409, 'email_already_registered');

        $sameSlug = $this->anonymous()->post('/api/auth/sign-up', ['email' => self::unique('new').'@api-test.example', 'password' => self::PASSWORD, 'displayName' => 'X', 'organizerName' => 'X', 'slug' => $org['slug']]);
        $this->assertProblem($sameSlug, 409, 'organizer_slug_taken');
    }

    public function test_invalid_input_and_a_missing_csrf_header_are_refused(): void
    {
        $invalid = $this->anonymous()->post('/api/auth/sign-up', ['email' => 'nope', 'password' => 'short', 'displayName' => '', 'organizerName' => '', 'slug' => 'Bad Slug']);
        $problem = $this->assertProblem($invalid, 422, 'validation_failed');
        self::assertNotEmpty(Json::arr($problem, 'violations'));

        $noCsrf = $this->anonymous()->post('/api/auth/sign-up', ['email' => self::unique('c').'@api-test.example', 'password' => self::PASSWORD, 'displayName' => 'X', 'organizerName' => 'X', 'slug' => self::unique('c')], csrf: false);
        $this->assertProblem($noCsrf, 403, 'csrf_invalid');
    }

    public function test_a_new_organizer_is_invisible_to_other_owners(): void
    {
        $a = $this->signUp('iso-a');
        $b = $this->signUp('iso-b');

        $this->assertProblem($b['client']->get("/api/admin/org/{$a['slug']}"), 404, 'not_found');
        $this->assertProblem($b['client']->get("/api/admin/org/{$a['slug']}/profile"), 404, 'not_found');
    }
}
