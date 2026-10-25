<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Shared;

use Eventor\Identity\Application\CreateOwner\CreateOwner;
use Eventor\Identity\Domain\Organizer\SlugAlreadyTaken;
use Eventor\Shared\Application\Idempotency\IdempotencyKeyReused;
use Eventor\Shared\Application\Idempotency\IdempotencyStore;
use Eventor\Shared\Application\Idempotency\RequestInProgress;
use Eventor\Shared\Application\Messaging\Stamp\IdempotencyStamp;
use Eventor\Tests\Support\IntegrationTestCase;

/**
 * Idempotency of unsafe commands (chapter 08): same key + same body replays the stored result, same key + other body
 * is a conflict, a failed command frees the key.
 */
final class IdempotencyTest extends IntegrationTestCase
{
    public function test_replaying_the_same_request_returns_the_first_result_and_runs_the_handler_once(): void
    {
        $command = new CreateOwner('idem@example.test', self::PASSWORD, 'Idem', 'Idempotent Club', 'idempotent-club');
        $stamp = IdempotencyStamp::for('test:create-owner', 'key-0123456789abcdef', ['slug' => 'idempotent-club']);

        $first = $this->commandBus()->dispatch($command, $stamp);
        $second = $this->commandBus()->dispatch($command, $stamp);

        self::assertSame($first, $second);
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM identity.organizer WHERE slug = 'idempotent-club'"));
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM audit.audit_entry WHERE action = 'organizer.registered'"));
        self::assertSame('completed', $this->connection()->fetchOne("SELECT status FROM shared.idempotency_record WHERE idempotency_key = 'key-0123456789abcdef'"));
    }

    public function test_the_same_key_with_a_different_body_is_a_conflict(): void
    {
        $this->commandBus()->dispatch(
            new CreateOwner('one@example.test', self::PASSWORD, 'One', 'Club One', 'club-one'),
            IdempotencyStamp::for('test:create-owner', 'key-conflict-0123456', ['slug' => 'club-one']),
        );

        $this->expectException(IdempotencyKeyReused::class);

        $this->commandBus()->dispatch(
            new CreateOwner('two@example.test', self::PASSWORD, 'Two', 'Club Two', 'club-two'),
            IdempotencyStamp::for('test:create-owner', 'key-conflict-0123456', ['slug' => 'club-two']),
        );
    }

    public function test_the_same_key_in_another_scope_is_independent(): void
    {
        $key = 'key-scope-0123456789';
        $first = $this->commandBus()->dispatch(
            new CreateOwner('a@example.test', self::PASSWORD, 'A', 'Club A', 'club-a'),
            IdempotencyStamp::for('scope-a', $key, ['n' => 1]),
        );
        $second = $this->commandBus()->dispatch(
            new CreateOwner('b@example.test', self::PASSWORD, 'B', 'Club B', 'club-b'),
            IdempotencyStamp::for('scope-b', $key, ['n' => 1]),
        );

        self::assertNotSame($first, $second);
    }

    public function test_a_failed_command_frees_the_key_so_the_client_can_retry(): void
    {
        $this->createOwner('first@example.test', 'taken-slug');
        $stamp = IdempotencyStamp::for('test:create-owner', 'key-retry-0123456789', ['slug' => 'taken-slug']);

        try {
            $this->commandBus()->dispatch(new CreateOwner('late@example.test', self::PASSWORD, 'Late', 'Late', 'taken-slug'), $stamp);
            self::fail('The slug is taken, the command must fail.');
        } catch (SlugAlreadyTaken) {
            // expected
        }

        self::assertSame(0, $this->dbInt("SELECT count(*) FROM shared.idempotency_record WHERE idempotency_key = 'key-retry-0123456789'"));
    }

    public function test_a_request_that_is_still_running_is_reported_as_in_progress(): void
    {
        $store = $this->service(IdempotencyStore::class);
        $hash = hash('sha256', 'body');

        self::assertNull($store->begin('scope', 'key-in-progress-0123456', $hash), 'the first caller must execute the command');

        $this->expectException(RequestInProgress::class);
        $store->begin('scope', 'key-in-progress-0123456', $hash);
    }

    public function test_expired_records_are_replaced(): void
    {
        $store = $this->service(IdempotencyStore::class);
        $hash = hash('sha256', 'body');
        $store->begin('scope', 'key-expired-01234567890', $hash);
        $store->complete('scope', 'key-expired-01234567890', ['n' => 1]);
        $this->connection()->executeStatement("UPDATE shared.idempotency_record SET expires_at = now() - interval '1 hour' WHERE idempotency_key = 'key-expired-01234567890'");

        self::assertNull($store->begin('scope', 'key-expired-01234567890', hash('sha256', 'another body')), 'an expired key may be reused with any body');
    }
}
