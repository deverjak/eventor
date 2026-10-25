<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Identity\Support;

use Eventor\Tests\Support\HttpTestCase;

/**
 * HTTP test helpers of the Identity module: team flows through the real endpoints.
 */
abstract class IdentityHttpTestCase extends HttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RecordingEventBus::reset();
    }

    /**
     * The owner invites `$email` and the invitee accepts anonymously (creating the account); afterwards the browser is
     * signed in as the new member.
     *
     * @param list<string> $eventScope
     *
     * @return array{membershipId: string, token: string}
     */
    protected function joinAs(string $ownerEmail, string $slug, string $email, string $role, array $eventScope = []): array
    {
        $this->login($ownerEmail);
        $token = $this->invite($slug, $email, $role, $eventScope);
        $this->logout();

        $this->request('POST', '/api/auth/invitations/accept', ['token' => $token, 'password' => self::PASSWORD, 'displayName' => 'Member']);
        self::assertResponseStatusCodeSame(200, (string) $this->client->getResponse()->getContent());

        return ['membershipId' => $this->dbString('SELECT m.id FROM identity.membership m JOIN identity.user_account u ON u.id = m.user_account_id WHERE u.email = :e', ['e' => $email]), 'token' => $token];
    }

    /**
     * @param list<string> $eventScope
     */
    protected function invite(string $slug, string $email, string $role, array $eventScope = []): string
    {
        $body = $this->request('POST', "/api/admin/org/$slug/invitations", ['email' => $email, 'role' => $role, 'eventScope' => $eventScope]);
        self::assertResponseStatusCodeSame(201, (string) $this->client->getResponse()->getContent());
        \assert(\is_array($body) && \is_string($body['acceptToken'] ?? null));

        return $body['acceptToken'];
    }

    protected function logout(): void
    {
        $this->request('POST', '/api/auth/logout');
        $this->client->getCookieJar()->clear();
    }

    /**
     * Integration events of a class that Identity dispatched on `event.bus` (the outbox INSERT happens there, in the
     * command transaction; the recorder sees events whose subscribers are still pending as well).
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    protected function published(string $class): array
    {
        return RecordingEventBus::dispatched($class);
    }
}
