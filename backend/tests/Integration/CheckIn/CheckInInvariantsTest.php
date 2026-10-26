<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\CheckIn;

use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Eventor\Tests\Support\IntegrationTestCase;

/**
 * CHK-06 / S10 at the lowest level: the invariants live in the database, whatever the application does. One valid
 * check-in per participation (partial unique index), and the application role can only void a row, never rewrite it.
 */
final class CheckInInvariantsTest extends IntegrationTestCase
{
    private string $organizer;

    private string $list;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organizer = '0199a000-0000-7000-8000-0000000000a1';
        $this->list = '0199a000-0000-7000-8000-0000000000a2';
        $this->connection()->executeStatement(
            'INSERT INTO checkin.check_in_list (id, organizer_id, event_id, activity_id, name, created_at) VALUES (?, ?, ?, ?, ?, now())',
            [$this->list, $this->organizer, '0199a000-0000-7000-8000-0000000000a3', '0199a000-0000-7000-8000-0000000000a4', 'Trail'],
        );
    }

    private function insert(string $id, string $participation, ?string $corrects = null): void
    {
        $this->connection()->executeStatement(
            'INSERT INTO checkin.check_in (id, organizer_id, participation_id, list_id, checked_in_at, corrects_id) VALUES (?, ?, ?, ?, now(), ?)',
            [$id, $this->organizer, $participation, $this->list, $corrects],
        );
    }

    public function test_a_second_valid_check_in_of_the_same_participation_is_refused_by_the_database(): void
    {
        $participation = '0199a000-0000-7000-8000-0000000000b1';
        $this->insert('0199a000-0000-7000-8000-0000000000c1', $participation);

        $this->assertRejected(UniqueConstraintViolationException::class, fn() => $this->insert('0199a000-0000-7000-8000-0000000000c2', $participation));
    }

    public function test_after_a_void_the_participation_may_check_in_again(): void
    {
        $participation = '0199a000-0000-7000-8000-0000000000b2';
        $this->insert('0199a000-0000-7000-8000-0000000000c3', $participation);
        $this->connection()->executeStatement("UPDATE checkin.check_in SET voided_at = now(), voided_by = 'manager', void_reason = 'Mistake' WHERE id = ?", ['0199a000-0000-7000-8000-0000000000c3']);

        $this->insert('0199a000-0000-7000-8000-0000000000c4', $participation, '0199a000-0000-7000-8000-0000000000c3');

        self::assertSame(2, $this->dbInt('SELECT count(*) FROM checkin.check_in WHERE participation_id = ?', [$participation]));
        self::assertSame(1, $this->dbInt('SELECT count(*) FROM checkin.check_in WHERE participation_id = ? AND voided_at IS NULL', [$participation]));
    }

    public function test_different_participations_check_in_independently(): void
    {
        $this->insert('0199a000-0000-7000-8000-0000000000c5', '0199a000-0000-7000-8000-0000000000b3');
        $this->insert('0199a000-0000-7000-8000-0000000000c6', '0199a000-0000-7000-8000-0000000000b4');

        self::assertSame(2, $this->dbInt('SELECT count(*) FROM checkin.check_in WHERE list_id = ?', [$this->list]));
    }

    public function test_a_void_needs_a_reason_and_a_reason_needs_a_void(): void
    {
        $this->insert('0199a000-0000-7000-8000-0000000000c7', '0199a000-0000-7000-8000-0000000000b5');

        $this->assertRejected(DriverException::class, fn() => $this->connection()->executeStatement('UPDATE checkin.check_in SET voided_at = now() WHERE id = ?', ['0199a000-0000-7000-8000-0000000000c7']));
    }

    public function test_the_application_cannot_rewrite_an_arrival(): void
    {
        $this->insert('0199a000-0000-7000-8000-0000000000c8', '0199a000-0000-7000-8000-0000000000b6');

        $this->assertRejected(DriverException::class, fn() => $this->connection()->executeStatement('UPDATE checkin.check_in SET checked_in_at = now() - interval \'1 day\' WHERE id = ?', ['0199a000-0000-7000-8000-0000000000c8']), '/permission denied/i');
    }

    public function test_the_application_cannot_delete_an_arrival(): void
    {
        $this->insert('0199a000-0000-7000-8000-0000000000c9', '0199a000-0000-7000-8000-0000000000b7');

        $this->assertRejected(DriverException::class, fn() => $this->connection()->executeStatement('DELETE FROM checkin.check_in WHERE id = ?', ['0199a000-0000-7000-8000-0000000000c9']), '/permission denied/i');
    }

    public function test_one_list_per_activity(): void
    {
        $this->assertRejected(UniqueConstraintViolationException::class, fn() => $this->connection()->executeStatement(
            'INSERT INTO checkin.check_in_list (id, organizer_id, event_id, activity_id, name, created_at) VALUES (?, ?, ?, ?, ?, now())',
            ['0199a000-0000-7000-8000-0000000000a5', $this->organizer, '0199a000-0000-7000-8000-0000000000a3', '0199a000-0000-7000-8000-0000000000a4', 'Duplicate'],
        ));
    }

    /** @param class-string<\Throwable> $exception */
    private function assertRejected(string $exception, callable $statement, ?string $message = null): void
    {
        $connection = $this->connection();
        $connection->createSavepoint('checkin_invariant');
        try {
            $statement();
            self::fail('The database statement should have been rejected.');
        } catch (\Throwable $error) {
            $connection->rollbackSavepoint('checkin_invariant');
            self::assertInstanceOf($exception, $error);
            if (null !== $message) {
                self::assertMatchesRegularExpression($message, $error->getMessage());
            }
        }
    }
}
