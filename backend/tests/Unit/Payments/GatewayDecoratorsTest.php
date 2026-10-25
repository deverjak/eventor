<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Payments;

use Eventor\Payments\Application\Port\Exception\PaymentGatewayRejected;
use Eventor\Payments\Application\Port\Exception\PaymentGatewayUnavailable;
use Eventor\Payments\Application\Port\GatewayAlerts;
use Eventor\Payments\Application\Port\PaymentRequestSpec;
use Eventor\Payments\Infrastructure\Pairrot\Decorator\CircuitBreakerGateway;
use Eventor\Payments\Infrastructure\Pairrot\Decorator\PiiRedactingLoggingGateway;
use Eventor\Payments\Infrastructure\Pairrot\Decorator\RetryingGateway;
use Eventor\Payments\Infrastructure\Pairrot\InMemoryPaymentGateway;
use Eventor\Shared\Application\Audit\AuditContext;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Money;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;

/**
 * Behaviour of the gateway decorator chain (retry -> circuit breaker -> logging) in front of the in-memory Pairrot.
 */
final class GatewayDecoratorsTest extends TestCase
{
    private MockClock $clock;
    private InMemoryPaymentGateway $fake;
    private RecordingAlerts $alerts;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-01 12:00:00');
        $this->fake = new InMemoryPaymentGateway();
        $this->alerts = new RecordingAlerts();
    }

    public function test_retry_repeats_a_retryable_failure_twice_with_growing_pauses_and_then_succeeds(): void
    {
        $this->fake->failNext(2);
        $retrying = new RetryingGateway($this->fake, $this->clock);
        $started = $this->clock->now();

        $subtenant = $retrying->createSubtenant('ext-1', 'Club', 'club@example.test');

        self::assertSame('ext-1', $subtenant->externalId);
        self::assertSame(3, $this->fake->countCalls('create_subtenant'));
        $waited = (float) $this->clock->now()->format('U.u') - (float) $started->format('U.u');
        self::assertGreaterThan(0.7, $waited, 'about 200 ms + 800 ms, jittered');
        self::assertLessThan(1.3, $waited);
    }

    public function test_retry_gives_up_after_the_third_attempt(): void
    {
        $this->fake->outage();
        $retrying = new RetryingGateway($this->fake, $this->clock);

        try {
            $retrying->findSubtenant('x');
            self::fail('Unavailable expected');
        } catch (PaymentGatewayUnavailable) {
            self::assertSame(3, $this->fake->countCalls('find_subtenant'));
        }
    }

    public function test_retry_does_not_repeat_a_permanent_refusal(): void
    {
        $retrying = new RetryingGateway($this->fake, $this->clock);

        try {
            $retrying->getRequest('s', 'unknown');
            self::fail('Rejected expected');
        } catch (PaymentGatewayRejected $e) {
            self::assertSame(404, $e->status);
            self::assertSame(1, $this->fake->countCalls('get_request'));
        }
    }

    public function test_the_breaker_opens_after_five_failures_and_stops_calling_pairrot(): void
    {
        $this->fake->outage();
        $breaker = $this->breaker();

        for ($i = 0; $i < CircuitBreakerGateway::THRESHOLD; ++$i) {
            $this->expectUnavailable(static fn() => $breaker->findSubtenant('x'));
        }
        self::assertSame(5, $this->fake->countCalls('find_subtenant'));
        self::assertCount(1, $this->alerts->opened, 'the opening is reported once');

        $this->expectUnavailable(static fn() => $breaker->findSubtenant('x'));
        self::assertSame(5, $this->fake->countCalls('find_subtenant'), 'an open circuit makes no network attempt');
    }

    public function test_the_breaker_lets_one_trial_through_after_the_pause_and_closes_on_success(): void
    {
        $this->fake->outage();
        $breaker = $this->breaker();
        for ($i = 0; $i < 5; ++$i) {
            $this->expectUnavailable(static fn() => $breaker->findSubtenant('x'));
        }

        $this->fake->outage(false);
        $this->clock->sleep(CircuitBreakerGateway::OPEN_SECONDS + 1);

        self::assertNull($breaker->findSubtenant('x'));
        self::assertNull($breaker->findSubtenant('x'), 'closed again');
        self::assertSame(7, $this->fake->countCalls('find_subtenant'));
    }

    public function test_a_failed_trial_reopens_the_circuit_at_once(): void
    {
        $this->fake->outage();
        $breaker = $this->breaker();
        for ($i = 0; $i < 5; ++$i) {
            $this->expectUnavailable(static fn() => $breaker->findSubtenant('x'));
        }
        $this->clock->sleep(CircuitBreakerGateway::OPEN_SECONDS + 1);

        $this->expectUnavailable(static fn() => $breaker->findSubtenant('x')); // the trial fails
        $this->expectUnavailable(static fn() => $breaker->findSubtenant('x')); // refused without a call

        self::assertSame(6, $this->fake->countCalls('find_subtenant'));
        self::assertCount(2, $this->alerts->opened);
    }

    public function test_a_rejection_does_not_count_as_an_outage(): void
    {
        $breaker = $this->breaker();

        for ($i = 0; $i < 8; ++$i) {
            try {
                $breaker->getRequest('s', 'unknown');
            } catch (PaymentGatewayRejected) {
            }
        }

        self::assertSame(8, $this->fake->countCalls('get_request'));
        self::assertSame([], $this->alerts->opened);
    }

    public function test_the_log_has_the_operation_and_external_id_but_never_the_iban_or_the_message(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<array{level: string, message: string, context: array<array-key, mixed>}> */
            public array $records = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->records[] = ['level' => \is_string($level) ? $level : 'unknown', 'message' => (string) $message, 'context' => $context];
            }
        };
        $subtenant = $this->fake->createSubtenant('ext-2', 'Club', 'club@example.test');
        $connection = $this->fake->connectBankAccount($subtenant->id, 'CZ6508000000192000145399', 'Main');
        $logging = new PiiRedactingLoggingGateway($this->fake, $logger, new AuditContext());

        $logging->createRequest(new PaymentRequestSpec($subtenant->id, $connection->id, 'order_x_1', Money::of(30_000), 'EV-2026-000001', 'Order x', new \DateTimeImmutable('2026-11-05T12:00:00+00:00')));

        self::assertCount(1, $logger->records);
        $dump = json_encode($logger->records, \JSON_THROW_ON_ERROR);
        self::assertStringContainsString('create_request', $dump);
        self::assertStringContainsString('order_x_1', $dump);
        self::assertStringNotContainsString('CZ6508', $dump);
        self::assertStringNotContainsString('EV-2026-000001', $dump);
        self::assertStringNotContainsString('SPD*', $dump);
    }

    private function breaker(): CircuitBreakerGateway
    {
        return new CircuitBreakerGateway($this->fake, new ArrayAdapter(), $this->clock, $this->alerts);
    }

    private function expectUnavailable(callable $call): void
    {
        try {
            $call();
            self::fail('PaymentGatewayUnavailable expected');
        } catch (PaymentGatewayUnavailable) {
            $this->addToAssertionCount(1);
        }
    }
}

final class RecordingAlerts implements GatewayAlerts
{
    /** @var list<\DateTimeImmutable> */
    public array $opened = [];

    public function circuitOpened(\DateTimeImmutable $since, \DateTimeImmutable $reopensAt): void
    {
        $this->opened[] = $since;
    }

    public function anomaly(?OrganizerId $organizer, string $reason): void {}
}
