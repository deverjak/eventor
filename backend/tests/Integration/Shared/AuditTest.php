<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Shared;

use Eventor\Audit\Contract\AuditTrail;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Tests\Support\IntegrationTestCase;

/**
 * Audit is written in the transaction of the change, with the actor and the correlation id of the unit of work.
 */
final class AuditTest extends IntegrationTestCase
{
    public function test_a_command_leaves_audit_entries_with_actor_and_correlation_id(): void
    {
        $ids = $this->createOwner('audit@example.test', 'audit-club');

        $trail = $this->service(AuditTrail::class)->forEntity(OrganizerId::fromString($ids['organizerId']), 'organizer', $ids['organizerId']);

        self::assertCount(1, $trail);
        self::assertSame('organizer.registered', $trail[0]->action);
        self::assertSame('SYSTEM', $trail[0]->actorType);
        self::assertSame('console', $trail[0]->actorLabel, 'no HTTP request and no user: the console is the actor');
        self::assertEquals(['slug' => 'audit-club', 'name' => 'Audit-club'], $trail[0]->after); // jsonb does not keep key order
        self::assertNotSame('', $trail[0]->correlationId);

        $membership = $this->service(AuditTrail::class)->forEntity(OrganizerId::fromString($ids['organizerId']), 'membership', $ids['membershipId']);
        self::assertCount(1, $membership);
        self::assertSame($trail[0]->correlationId, $membership[0]->correlationId, 'one command, one correlation id');
        self::assertEquals(['userAccountId' => $ids['userAccountId'], 'role' => 'OWNER'], $membership[0]->after);
    }

    public function test_the_audit_entry_of_an_organizer_never_contains_personal_data(): void
    {
        $this->createOwner('private.person@example.test', 'pii-club');

        $json = $this->dbString('SELECT string_agg(coalesce(before::text, \'\') || coalesce(after::text, \'\'), \' \') FROM audit.audit_entry');

        self::assertStringNotContainsString('private.person', $json);
        self::assertStringNotContainsString('@', $json);
    }
}
