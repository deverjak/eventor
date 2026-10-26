<?php

declare(strict_types=1);

namespace Eventor\Tests\Scenario\CheckIn;

use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\CheckInListId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\FormVersionId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Id\PersonId;
use Eventor\Shared\Domain\Id\VariantId;
use Eventor\Tests\Support\Concurrency\ConcurrencyHarness;
use Eventor\Tests\Support\Concurrency\ConcurrencyResults;
use Eventor\Tests\Support\IntegrationTestCase;

/**
 * S10 (chapter 10): two operators scan the same participant at the same moment. Real operating-system processes, real
 * commits, no dama rollback (the processes must see each other's rows). Exactly one check-in survives, the others are
 * told "already checked in" with the time and the winner, and the database holds one valid row.
 *
 * The rows the processes commit (and the participation they check in) are removed in tearDown with the owner role,
 * because the application role may neither delete nor rewrite a check-in.
 */
#[SkipDatabaseRollback]
final class S10ConcurrentCheckInScenarioTest extends IntegrationTestCase
{
    private string $organizer;

    private string $event;

    private string $activity;

    private string $list;

    private string $participation;

    private string $order;

    private string $person;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organizer = OrganizerId::generate()->toString();
        $this->event = EventId::generate()->toString();
        $this->activity = ActivityId::generate()->toString();
        $this->list = CheckInListId::generate()->toString();
        $this->participation = ParticipationId::generate()->toString();
        $this->order = OrderId::generate()->toString();
        $this->person = PersonId::generate()->toString();
        $this->seed();
    }

    protected function tearDown(): void
    {
        $owner = $this->ownerConnection();
        try {
            $owner->executeStatement('DELETE FROM checkin.check_in WHERE organizer_id = ?', [$this->organizer]);
            $owner->executeStatement('DELETE FROM checkin.check_in_list WHERE organizer_id = ?', [$this->organizer]);
            $owner->executeStatement('DELETE FROM registration.participation WHERE organizer_id = ?', [$this->organizer]);
            $owner->executeStatement('DELETE FROM registration.person WHERE organizer_id = ?', [$this->organizer]);
            $owner->executeStatement('DELETE FROM registration.orders WHERE organizer_id = ?', [$this->organizer]);
        } finally {
            $owner->close();
        }
        parent::tearDown();
    }

    /**
     * A confirmed participation on its own list, committed. Payments knows nothing of the order, which the desk reads
     * as unpaid, so the racing operators acknowledge that warning.
     */
    private function seed(): void
    {
        $now = '2026-11-21 08:00:00+00';
        $this->connection()->executeStatement(
            'INSERT INTO registration.orders (id, organizer_id, event_id, number, orderer_email, payment_method, amount_due_minor, amount_due_currency, form_version_id, placed_at) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?)',
            [$this->order, $this->organizer, $this->event, 'S10-'.substr($this->order, -12), 'race@example.test', 'cash', 'CZK', FormVersionId::generate()->toString(), $now],
        );
        $this->connection()->executeStatement('INSERT INTO registration.person (id, organizer_id, order_id) VALUES (?, ?, ?)', [$this->person, $this->organizer, $this->order]);
        $this->connection()->executeStatement(
            "INSERT INTO registration.participation (id, organizer_id, order_id, person_id, event_id, activity_id, variant_id, state, legal_state, answers_state, registration_code, price_minor, price_currency, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'confirmed', 'complete', 'complete', ?, 0, 'CZK', ?)",
            [$this->participation, $this->organizer, $this->order, $this->person, $this->event, $this->activity, VariantId::generate()->toString(), strtoupper(substr($this->participation, -10)), $now],
        );
        $this->connection()->executeStatement(
            'INSERT INTO checkin.check_in_list (id, organizer_id, event_id, activity_id, name, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$this->list, $this->organizer, $this->event, $this->activity, 'Trail 5 km', $now],
        );
    }

    private function ownerConnection(): Connection
    {
        $url = $_SERVER['DATABASE_MIGRATIONS_URL'] ?? $_ENV['DATABASE_MIGRATIONS_URL'] ?? getenv('DATABASE_MIGRATIONS_URL');
        self::assertIsString($url, 'DATABASE_MIGRATIONS_URL must be set (compose sets it).');
        $token = $_SERVER['TEST_TOKEN'] ?? 'default';
        $params = (new DsnParser(['postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql']))->parse($url);
        $params['dbname'] = 'eventor_test_'.(\is_string($token) && '' !== $token ? $token : 'default');

        return DriverManager::getConnection($params);
    }

    private function race(int $processes): ConcurrencyResults
    {
        return ConcurrencyHarness::run(
            $this->connection(),
            $processes,
            'eventor:test:check-in',
            fn(int $i): array => [
                '--organizer='.$this->organizer,
                '--event='.$this->event,
                '--list='.$this->list,
                '--participation='.$this->participation,
                '--operator=operator-'.$i,
            ],
        );
    }

    private function validRows(): int
    {
        return $this->dbInt('SELECT count(*) FROM checkin.check_in WHERE participation_id = ? AND voided_at IS NULL', [$this->participation]);
    }

    public function test_eight_operators_scanning_the_same_participant_produce_exactly_one_check_in_s10(): void
    {
        $results = $this->race(8);

        self::assertCount(1, $results->succeeded(), $results->describe());
        self::assertCount(7, $results->failedWith('already_checked_in'), $results->describe());
        self::assertSame([], $results->unexpected(['already_checked_in']), $results->describe());
        self::assertSame(1, $this->validRows());
        self::assertSame(1, $this->dbInt('SELECT count(*) FROM checkin.check_in WHERE participation_id = ?', [$this->participation]), 'the losers wrote nothing');

        $winner = $results->succeeded()[0];
        $winnerOperator = 'operator-'.$winner->index;
        self::assertSame($winnerOperator, $this->dbString('SELECT checked_in_by FROM checkin.check_in WHERE participation_id = ?', [$this->participation]));
        foreach ($results->failedWith('already_checked_in') as $loser) {
            $context = $loser->report['context'] ?? null;
            self::assertIsArray($context, $loser->describe());
            self::assertSame($winnerOperator, $context['checkedInBy'] ?? null, 'the loser is told who won: '.$loser->describe());
            self::assertNotNull($context['checkedInAt'] ?? null, 'and when');
        }
    }

    public function test_after_a_void_the_participant_can_be_raced_for_again(): void
    {
        $first = $this->race(4);
        self::assertCount(1, $first->succeeded(), $first->describe());
        $this->connection()->executeStatement("UPDATE checkin.check_in SET voided_at = now(), voided_by = 'manager', void_reason = 'Scanned by mistake' WHERE participation_id = ?", [$this->participation]);
        self::assertSame(0, $this->validRows());

        $second = $this->race(4);

        self::assertCount(1, $second->succeeded(), $second->describe());
        self::assertCount(3, $second->failedWith('already_checked_in'), $second->describe());
        self::assertSame(1, $this->validRows());
        self::assertSame(2, $this->dbInt('SELECT count(*) FROM checkin.check_in WHERE participation_id = ?', [$this->participation]), 'the voided row stays beside the new one');
    }
}
