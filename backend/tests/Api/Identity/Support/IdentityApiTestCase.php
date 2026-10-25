<?php

declare(strict_types=1);

namespace Eventor\Tests\Api\Identity\Support;

use Eventor\Tests\Api\Support\ApiClient;
use Eventor\Tests\Api\Support\ApiTestCase;
use Eventor\Tests\Integration\Identity\Support\Json;

/**
 * Black-box helpers of the Identity API tests: everything goes through public endpoints, with unique slugs and e-mails
 * per test so tests never collide in the shared `eventor_api` database.
 */
abstract class IdentityApiTestCase extends ApiTestCase
{
    public const string PASSWORD = 'correct-horse-battery';

    protected static function unique(string $prefix): string
    {
        return $prefix.'-'.bin2hex(random_bytes(5));
    }

    /**
     * Registers a new organizer through the public sign-up; the returned client is signed in as its owner.
     *
     * @return array{client: ApiClient, email: string, slug: string}
     */
    protected function signUp(string $prefix = 'org'): array
    {
        $slug = self::unique($prefix);
        $email = $slug.'@api-test.example';
        $client = $this->anonymous();

        $response = $client->post('/api/auth/sign-up', [
            'email' => $email,
            'password' => self::PASSWORD,
            'displayName' => 'Owner '.$slug,
            'organizerName' => 'Org '.$slug,
            'slug' => $slug,
        ]);
        self::assertSame(201, $response->status, $response->body);

        return ['client' => $client, 'email' => $email, 'slug' => $slug];
    }

    /**
     * The owner invites; returns the accept token (the API stack is not production, so it is handed back).
     *
     * @param list<string> $eventScope
     */
    protected function invite(ApiClient $owner, string $slug, string $email, string $role, array $eventScope = []): string
    {
        $response = $owner->post("/api/admin/org/$slug/invitations", ['email' => $email, 'role' => $role, 'eventScope' => $eventScope]);
        self::assertSame(201, $response->status, $response->body);

        return Json::str($response->json(), 'acceptToken');
    }

    /**
     * Owner invites, the invitee accepts anonymously and is signed in; returns that client and the membership id.
     *
     * @param array{client: ApiClient, email: string, slug: string} $org
     * @param list<string>                                          $eventScope
     *
     * @return array{client: ApiClient, email: string, membershipId: string}
     */
    protected function addMember(array $org, string $role, array $eventScope = []): array
    {
        $email = self::unique(strtolower($role)).'@api-test.example';
        $token = $this->invite($org['client'], $org['slug'], $email, $role, $eventScope);

        $client = $this->anonymous();
        $accepted = $client->post('/api/auth/invitations/accept', ['token' => $token, 'password' => self::PASSWORD, 'displayName' => 'Member']);
        self::assertSame(200, $accepted->status, $accepted->body);

        $members = Json::rows($org['client']->get("/api/admin/org/{$org['slug']}/members")->json(), 'items');

        return ['client' => $client, 'email' => $email, 'membershipId' => Json::str(Json::where($members, 'email', $email), 'membershipId')];
    }
}
