<?php

declare(strict_types=1);

namespace Eventor\Tests\Scenario;

use Eventor\Identity\Contract\Event\MembershipChanged;
use Eventor\Identity\Domain\Event\MembershipGranted;
use Eventor\Shared\Application\Messaging\Stamp\IdempotencyStamp;
use Eventor\Tests\Fixtures\Messenger\ProbeRecorder;

/**
 * Phase F0 exit criterion: "a test command writes state, audit and outbox in one transaction and a subscriber
 * receives it (including retry and dead letter)". The command is `CreateOwner`; the subscribers are the test
 * fixtures in tests/Fixtures/Messenger on three real Doctrine transports (the outbox):
 *
 *   probe_outbox    always succeeds
 *   probe_flaky     fails twice, succeeds on the third attempt
 *   probe_failing   fails every time -> after six attempts: failed transport + AttentionItem
 */
final class F0_TransactionalOutboxTest extends ScenarioTestCase
{
    private const array PROBE_QUEUES = ['probe_outbox', 'probe_flaky', 'probe_failing'];

    public function test_state_audit_and_outbox_are_written_by_one_command_in_one_transaction(): void
    {
        $ids = $this->createOwner('outbox@example.test', 'outbox-club');

        // state
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM identity.organizer WHERE slug = 'outbox-club'"));
        // audit (written by the same command, same correlation id)
        self::assertSame(2, $this->dbInt('SELECT count(*) FROM audit.audit_entry WHERE organizer_id = ?', [$ids['organizerId']]));
        self::assertSame(1, $this->dbInt('SELECT count(DISTINCT correlation_id) FROM audit.audit_entry WHERE organizer_id = ?', [$ids['organizerId']]));
        // outbox: one row per subscribing transport (each module gets its own copy with its own retry counter)
        $queues = $this->dbColumn('SELECT queue_name FROM shared.messenger_messages ORDER BY queue_name');
        self::assertSame(['probe_failing', 'probe_flaky', 'probe_outbox'], $queues);

        $row = $this->dbRow("SELECT body, headers FROM shared.messenger_messages WHERE queue_name = 'probe_outbox'");
        self::assertIsString($row['body']);
        self::assertIsString($row['headers']);
        $body = json_decode($row['body'], true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(['organizerId' => $ids['organizerId'], 'membershipId' => $ids['membershipId'], 'userAccountId' => $ids['userAccountId'], 'role' => 'OWNER', 'change' => 'granted'], $body);
        self::assertStringNotContainsString('outbox@example.test', $row['body'], 'integration events carry ids, never personal data');
        self::assertStringContainsString('TenantStamp', $row['headers']);
        self::assertStringContainsString('CorrelationStamp', $row['headers']);
    }

    public function test_a_failure_after_the_event_was_published_rolls_back_state_audit_and_outbox_together(): void
    {
        $recorder = static::getContainer()->get(ProbeRecorder::class);
        \assert($recorder instanceof ProbeRecorder);
        $recorder->failAfterPublish = true;

        try {
            $this->createOwner('rollback@example.test', 'rollback-club');
            self::fail('The sync handler after the publisher throws; the command must fail.');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('after publishing', $e->getMessage());
        }

        self::assertSame(0, $this->dbInt("SELECT count(*) FROM identity.organizer WHERE slug = 'rollback-club'"), 'no state');
        self::assertSame(0, $this->dbInt('SELECT count(*) FROM audit.audit_entry'), 'no audit');
        self::assertSame(0, $this->dbInt('SELECT count(*) FROM shared.messenger_messages'), 'no phantom message in the outbox');
    }

    public function test_the_subscriber_receives_the_event_with_the_tenant_and_actor_restored(): void
    {
        $ids = $this->createOwner('delivery@example.test', 'delivery-club');

        $this->drainUntilQuiet();

        $recorder = static::getContainer()->get(ProbeRecorder::class);
        \assert($recorder instanceof ProbeRecorder);
        $received = $recorder->receivedBy('ok');
        self::assertCount(1, $received);
        self::assertInstanceOf(MembershipChanged::class, $received[0]['event']);
        self::assertSame($ids['membershipId'], $received[0]['event']->membershipId);
        self::assertSame($ids['organizerId'], $received[0]['tenant'], 'TenantContextMiddleware restored the tenant from the TenantStamp');
        self::assertSame('console', $received[0]['actor'], 'AuditContextMiddleware restored the actor from the ActorStamp');
    }

    public function test_a_failing_subscriber_is_retried_on_the_schedule_and_eventually_succeeds(): void
    {
        $this->createOwner('retry@example.test', 'retry-club');

        // First attempt: fails, the message is re-queued ~1 minute ahead (±10 %), nothing else is disturbed.
        $first = $this->queues()->drain(['probe_flaky']);
        self::assertSame(1, $first->failed);
        $seconds = $this->dbFloat("SELECT EXTRACT(EPOCH FROM (available_at - (now() AT TIME ZONE 'utc'))) FROM shared.messenger_messages WHERE queue_name = 'probe_flaky'");
        self::assertGreaterThan(45, $seconds);
        self::assertLessThan(75, $seconds, 'first retry waits about one minute');

        $this->drainUntilQuiet();

        $recorder = static::getContainer()->get(ProbeRecorder::class);
        \assert($recorder instanceof ProbeRecorder);
        self::assertSame(3, $recorder->attempts('flaky'), 'two failures, then success');
        self::assertCount(1, $recorder->receivedBy('flaky'));
        self::assertSame(0, $this->dbInt("SELECT count(*) FROM shared.messenger_messages WHERE queue_name = 'probe_flaky'"), 'acknowledged messages leave the queue');
    }

    public function test_a_message_that_always_fails_is_dead_lettered_after_six_attempts_and_raises_an_attention_item(): void
    {
        $ids = $this->createOwner('dead@example.test', 'dead-club');

        $this->drainUntilQuiet();

        $recorder = static::getContainer()->get(ProbeRecorder::class);
        \assert($recorder instanceof ProbeRecorder);
        self::assertSame(6, $recorder->attempts('failing'), 'one attempt plus five retries');

        $deadLetters = $this->queues()->deadLetters();
        self::assertCount(1, $deadLetters);
        self::assertInstanceOf(MembershipChanged::class, $deadLetters[0]);

        $items = $this->dbRows('SELECT type, state, organizer_id, subject_type, payload, dedupe_key FROM attention.attention_item');
        self::assertCount(1, $items);
        self::assertSame('outbox_dead_letter', $items[0]['type']);
        self::assertSame('OPEN', $items[0]['state']);
        self::assertSame($ids['organizerId'], $items[0]['organizer_id'], 'the item belongs to the tenant of the failed message');
        self::assertIsString($items[0]['payload']);
        $payload = json_decode($items[0]['payload'], true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertSame(MembershipChanged::class, $payload['message']);
        self::assertSame('probe_failing', $payload['transport']);
        self::assertSame(\RuntimeException::class, $payload['error']);
        self::assertStringNotContainsString('dead@example.test', $items[0]['payload']);
        self::assertIsString($items[0]['dedupe_key']);
        self::assertStringStartsWith('dead_letter:probe_failing:', $items[0]['dedupe_key']);
    }

    public function test_the_other_subscribers_are_not_affected_by_the_failing_one(): void
    {
        $this->createOwner('isolated@example.test', 'isolated-club');

        $this->drainUntilQuiet();

        $recorder = static::getContainer()->get(ProbeRecorder::class);
        \assert($recorder instanceof ProbeRecorder);
        self::assertCount(1, $recorder->receivedBy('ok'), 'one delivery to the healthy subscriber, no repeats caused by the failing one');
        self::assertSame(6, $recorder->attempts('failing'));
    }

    public function test_an_idempotent_replay_publishes_nothing_twice(): void
    {
        $stamp = IdempotencyStamp::for('scenario', 'replay-key-0123456789', ['slug' => 'replay-club']);
        $command = new \Eventor\Identity\Application\CreateOwner\CreateOwner('replay@example.test', self::PASSWORD, 'Replay', 'Replay', 'replay-club');

        $this->commandBus()->dispatch($command, $stamp);
        $this->commandBus()->dispatch($command, $stamp);

        self::assertSame(3, $this->dbInt('SELECT count(*) FROM shared.messenger_messages'), 'one event x three subscribing transports, not six');
    }

    public function test_the_domain_event_and_the_integration_event_are_different_things(): void
    {
        // MembershipGranted (Identity\Domain\Event) is internal; only MembershipChanged (Contract\Event) travels.
        $this->createOwner('kinds@example.test', 'kinds-club');

        $types = $this->dbColumn("SELECT DISTINCT headers::json->>'type' FROM shared.messenger_messages");
        self::assertSame([MembershipChanged::class], $types);
        self::assertNotContains(MembershipGranted::class, $types);
    }

    /**
     * Runs the worker until nothing is left. Retries are scheduled in the future (1 min ... 12 h), so after every round
     * the queue is made due, which is what waiting would do.
     */
    private function drainUntilQuiet(): void
    {
        for ($round = 0; $round < 12; ++$round) {
            $this->connection()->executeStatement("UPDATE shared.messenger_messages SET available_at = available_at - interval '2 days' WHERE queue_name IN ('".implode("','", self::PROBE_QUEUES)."')");
            $result = $this->drainQueues();
            if (0 === $result->handled && 0 === $result->failed) {
                return;
            }
        }

        self::fail('The queues did not become quiet within 12 rounds.');
    }
}
