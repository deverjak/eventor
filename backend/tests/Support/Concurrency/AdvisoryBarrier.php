<?php

declare(strict_types=1);

namespace Eventor\Tests\Support\Concurrency;

use Doctrine\DBAL\Connection;

/**
 * The starting gun of ConcurrencyHarness: a Postgres advisory lock. The test holds it exclusively, every subprocess
 * asks for it shared and therefore blocks; when the test releases it, all the waiting processes are granted at the
 * same instant and run their command together. The lock key stays below 2^31 so `pg_locks` shows it as `objid`.
 */
final class AdvisoryBarrier
{
    /** Subprocess side: connect first (so connecting is not part of the race), then wait for the gun. */
    public static function wait(Connection $connection, int $key): void
    {
        $connection->fetchOne('SELECT 1');
        $connection->fetchOne('SELECT pg_advisory_lock_shared(:key)', ['key' => $key]);
        $connection->fetchOne('SELECT pg_advisory_unlock_shared(:key)', ['key' => $key]);
    }

    /** Test side: take the lock exclusively before starting the processes. */
    public static function close(Connection $control, int $key): void
    {
        $control->fetchOne('SELECT pg_advisory_lock(:key)', ['key' => $key]);
    }

    /** How many sessions are blocked on the barrier right now. */
    public static function waiting(Connection $control, int $key): int
    {
        $count = $control->fetchOne(
            "SELECT count(*) FROM pg_locks WHERE locktype = 'advisory' AND NOT granted AND classid = 0 AND objid = :key AND objsubid = 1",
            ['key' => $key],
        );

        return is_numeric($count) ? (int) $count : 0;
    }

    /** Test side: fire. */
    public static function open(Connection $control, int $key): void
    {
        $control->fetchOne('SELECT pg_advisory_unlock(:key)', ['key' => $key]);
    }

    public static function newKey(): int
    {
        return random_int(1, 2_000_000_000);
    }
}
