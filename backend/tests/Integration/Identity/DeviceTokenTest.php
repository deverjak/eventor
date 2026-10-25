<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Identity;

use Eventor\Tests\Integration\Identity\Support\IdentityHttpTestCase;
use Eventor\Tests\Integration\Identity\Support\Json;

/**
 * Check-in device tokens (ADR 006): issue, list, revoke and the `check_in` firewall with GET /api/check-in/whoami.
 */
final class DeviceTokenTest extends IdentityHttpTestCase
{
    private const string LIST = '0199a000-0000-7000-8000-0000000000c1';

    /**
     * @param array<string, mixed> $body
     *
     * @return array{id: string, token: string}
     */
    private function issue(string $slug, array $body = ['label' => 'Gate A']): array
    {
        $result = $this->request('POST', "/api/admin/org/$slug/device-tokens", $body);
        self::assertResponseStatusCodeSame(201, (string) $this->client->getResponse()->getContent());

        return ['id' => Json::str($result, 'id'), 'token' => Json::str($result, 'token')];
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private function whoami(?string $token): ?array
    {
        $this->client->getCookieJar()->clear();

        return $this->request('GET', '/api/check-in/whoami', null, null === $token ? [] : ['Authorization' => 'Bearer '.$token]);
    }

    public function test_a_device_token_is_issued_once_hashed_and_authenticates_the_device(): void
    {
        $owner = $this->createOwner('jana@example.test', 'klub');
        $this->login('jana@example.test');

        $issued = $this->issue('klub', ['label' => 'Gate A', 'checkInListId' => self::LIST]);

        self::assertMatchesRegularExpression('/^dev1_'.$issued['id'].'_[A-Za-z0-9_-]{43}$/', $issued['token']);
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        $row = $this->dbRow('SELECT secret_sha256, check_in_list_id, organizer_id FROM identity.device_token');
        self::assertSame(hash('sha256', substr($issued['token'], \strlen('dev1_'.$issued['id'].'_'))), $row['secret_sha256']);
        self::assertStringNotContainsString($issued['token'], json_encode($row, \JSON_THROW_ON_ERROR));
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM audit.audit_entry WHERE action = 'device_token.issued'"));
        self::assertSame(0, $this->dbInt("SELECT count(*) FROM audit.audit_entry WHERE after::text LIKE '%dev1_%'"));

        $who = $this->whoami($issued['token']);

        self::assertResponseIsSuccessful();
        self::assertSame(['type' => 'device', 'deviceTokenId' => $issued['id'], 'organizerId' => $owner['organizerId'], 'checkInListId' => self::LIST, 'label' => 'Gate A'], $who);
    }

    public function test_a_team_member_with_a_session_reaches_the_same_endpoint(): void
    {
        $this->createOwner('jana@example.test', 'klub');
        $this->login('jana@example.test');

        $who = $this->request('GET', '/api/check-in/whoami');

        self::assertResponseIsSuccessful();
        self::assertSame('member', Json::at($who, 'type'));
        self::assertSame('jana@example.test', Json::at($who, 'profile', 'email'));
    }

    public function test_no_credentials_or_bad_tokens_are_401(): void
    {
        $this->createOwner('jana@example.test', 'klub');
        $this->login('jana@example.test');
        $issued = $this->issue('klub');
        $secret = substr($issued['token'], -43);

        $this->whoami(null);
        $this->assertProblem(401, 'unauthorized');

        foreach ([
            'dev1_'.$issued['id'].'_'.str_repeat('A', 43), // wrong secret
            'dev1_0199a000-0000-7000-8000-000000000000_'.$secret, // unknown id
            'dev1_garbage',
        ] as $bad) {
            $this->whoami($bad);
            $this->assertProblem(401, 'invalid_device_token');
        }
    }

    public function test_a_revoked_device_is_locked_out_at_once(): void
    {
        $this->createOwner('jana@example.test', 'klub');
        $this->login('jana@example.test');
        $issued = $this->issue('klub');
        $this->whoami($issued['token']);
        self::assertResponseIsSuccessful();

        $this->login('jana@example.test');
        $this->request('DELETE', '/api/admin/org/klub/device-tokens/'.$issued['id']);
        self::assertResponseStatusCodeSame(204);
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM audit.audit_entry WHERE action = 'device_token.revoked'"));

        $this->whoami($issued['token']);
        $this->assertProblem(401, 'invalid_device_token');
    }

    public function test_an_expired_token_is_refused(): void
    {
        $this->createOwner('jana@example.test', 'klub');
        $this->login('jana@example.test');
        $issued = $this->issue('klub', ['label' => 'Day pass', 'expiresAt' => (new \DateTimeImmutable('+1 day'))->format(\DATE_ATOM)]);
        $this->whoami($issued['token']);
        self::assertResponseIsSuccessful();

        self::mockTime('+2 days');
        $this->whoami($issued['token']);

        $this->assertProblem(401, 'invalid_device_token');
    }

    public function test_the_list_shows_state_but_never_the_secret(): void
    {
        $this->createOwner('jana@example.test', 'klub');
        $this->login('jana@example.test');
        $first = $this->issue('klub', ['label' => 'One']);
        $this->issue('klub', ['label' => 'Two', 'checkInListId' => self::LIST]);
        $this->request('DELETE', '/api/admin/org/klub/device-tokens/'.$first['id']);

        $list = $this->request('GET', '/api/admin/org/klub/device-tokens');

        $items = Json::rows($list, 'items');
        self::assertCount(2, $items);
        $one = Json::where($items, 'label', 'One');
        $two = Json::where($items, 'label', 'Two');
        self::assertFalse($one['active']);
        self::assertNotNull($one['revokedAt']);
        self::assertTrue($two['active']);
        self::assertSame(self::LIST, $two['checkInListId']);
        self::assertStringNotContainsString('dev1_', (string) $this->client->getResponse()->getContent());
    }

    public function test_a_device_cannot_use_the_admin_api(): void
    {
        $this->createOwner('jana@example.test', 'klub');
        $this->login('jana@example.test');
        $issued = $this->issue('klub');
        $this->client->getCookieJar()->clear();

        $this->request('GET', '/api/admin/org/klub', null, ['Authorization' => 'Bearer '.$issued['token']]);

        $this->assertProblem(401, 'unauthorized');
    }

    public function test_issuing_validates_input_and_needs_the_permission(): void
    {
        $this->createOwner('jana@example.test', 'klub');
        $this->login('jana@example.test');

        $this->request('POST', '/api/admin/org/klub/device-tokens', ['label' => '', 'checkInListId' => 'nope']);
        $this->assertProblem(422, 'validation_failed');
        $this->request('POST', '/api/admin/org/klub/device-tokens', ['label' => 'Late', 'expiresAt' => '2020-01-01T00:00:00+00:00']);
        $this->assertProblem(422, 'device_token_expiry_in_past');

        $this->request('DELETE', '/api/admin/org/klub/device-tokens/0199a000-0000-7000-8000-000000000000');
        $this->assertProblem(404, 'device_token_not_found');

        $this->joinAs('jana@example.test', 'klub', 'door@example.test', 'CHECK_IN');
        $this->request('POST', '/api/admin/org/klub/device-tokens', ['label' => 'Mine']);
        $this->assertProblem(403, 'forbidden');
        self::assertSame(0, $this->dbInt('SELECT count(*) FROM identity.device_token'));
    }

    public function test_a_token_of_another_organizer_cannot_be_revoked_through_my_url(): void
    {
        $this->createOwner('jana@example.test', 'klub');
        $this->createOwner('petr@example.test', 'cizi');
        $this->login('petr@example.test');
        $foreign = $this->issue('cizi');
        $this->logout();

        $this->login('jana@example.test');
        $this->request('DELETE', '/api/admin/org/klub/device-tokens/'.$foreign['id']);

        $this->assertProblem(404, 'device_token_not_found');
        self::assertSame(0, $this->dbInt('SELECT count(*) FROM identity.device_token WHERE revoked_at IS NOT NULL'));
    }
}
