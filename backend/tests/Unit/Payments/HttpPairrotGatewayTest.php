<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Payments;

use Eventor\Payments\Application\Port\AllocationRequest;
use Eventor\Payments\Application\Port\Exception\PaymentGatewayRejected;
use Eventor\Payments\Application\Port\Exception\PaymentGatewayUnavailable;
use Eventor\Payments\Application\Port\Exception\PaymentRequestConflict;
use Eventor\Payments\Application\Port\Exception\PaymentRequestHasAllocations;
use Eventor\Payments\Application\Port\PaymentRequestSpec;
use Eventor\Payments\Contract\Exception\BankAccountUnavailable;
use Eventor\Payments\Contract\ReceptionState;
use Eventor\Payments\Infrastructure\Pairrot\HttpPairrotGateway;
use Eventor\Shared\Domain\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * The adapter's wire format and error mapping against canned answers (the real Pairrot is covered by tests/Contract).
 */
final class HttpPairrotGatewayTest extends TestCase
{
    private const array REQUEST = [
        'id' => 'pr-1', 'external_id' => 'order_a_1', 'amount' => 45000, 'currency' => 'CZK', 'variable_symbol' => '2026000001',
        'account_number' => '19-2000145399/0800', 'iban' => 'CZ6508000000192000145399', 'due_at' => '2026-11-15T17:00:00+00:00',
        'status' => 'open', 'paid_amount' => 0, 'payment_change_sequence' => 0, 'version' => 1,
        'payment_instructions' => ['payload' => 'SPD*1.0*ACC:CZ6508000000192000145399*AM:450.00*CC:CZK*X-VS:2026000001'],
    ];

    /** @var list<array{method: string, url: string, options: array<mixed>}> */
    private array $sent = [];

    public function test_create_request_sends_the_idempotent_body_and_maps_the_instructions(): void
    {
        $gateway = $this->gateway(new MockResponse(json_encode(self::REQUEST + ['replayed' => false], \JSON_THROW_ON_ERROR), ['http_code' => 201]));

        $issued = $gateway->createRequest($this->spec());

        self::assertSame('pr-1', $issued->pairrotId);
        self::assertSame('2026000001', $issued->variableSymbol);
        self::assertSame('CZ6508000000192000145399', $issued->iban);
        self::assertStringStartsWith('SPD*1.0*', $issued->spaydPayload);
        self::assertFalse($issued->replayed);
        self::assertSame('POST', $this->sent[0]['method']);
        self::assertStringEndsWith('/v1/subtenants/sub-1/payment-requests', $this->sent[0]['url']);
        $body = $this->body(0);
        self::assertSame(['bank_connection_id' => 'bc-1', 'external_id' => 'order_a_1', 'amount' => 45000, 'currency' => 'CZK', 'message' => 'EV-2026-000123', 'description' => 'Order a', 'due_at' => '2026-11-15T18:00:00+01:00'], $body);
    }

    public function test_a_replay_is_a_success(): void
    {
        $gateway = $this->gateway(new MockResponse(json_encode(self::REQUEST + ['replayed' => true], \JSON_THROW_ON_ERROR), ['http_code' => 200]));

        self::assertTrue($gateway->createRequest($this->spec())->replayed);
    }

    public function test_a_different_body_for_the_same_external_id_is_a_conflict(): void
    {
        $gateway = $this->gateway($this->error(409, 'external_id_conflict'));

        $this->expectException(PaymentRequestConflict::class);
        $gateway->createRequest($this->spec());
    }

    #[DataProvider('unavailable')]
    public function test_server_errors_throttling_and_network_failures_are_retryable(MockResponse|\Throwable $answer): void
    {
        $gateway = $this->gateway($answer);

        $this->expectException(PaymentGatewayUnavailable::class);
        $gateway->getRequest('sub-1', 'pr-1');
    }

    /**
     * @return iterable<string, array{MockResponse|\Throwable}>
     */
    public static function unavailable(): iterable
    {
        yield '500' => [new MockResponse('{}', ['http_code' => 500])];
        yield '503' => [new MockResponse('', ['http_code' => 503])];
        yield '429' => [new MockResponse('{}', ['http_code' => 429])];
        yield '408' => [new MockResponse('{}', ['http_code' => 408])];
        yield 'network' => [new MockResponse([new TransportException('down')])];
        yield 'garbage' => [new MockResponse('<html>', ['http_code' => 200])];
    }

    public function test_other_client_errors_are_rejections_with_the_pairrot_code(): void
    {
        $gateway = $this->gateway($this->error(400, 'invalid_account_number'));

        try {
            $gateway->getRequest('sub-1', 'pr-1');
            self::fail('Rejected expected');
        } catch (PaymentGatewayRejected $e) {
            self::assertSame(400, $e->status);
            self::assertSame('invalid_account_number', $e->errorCode());
        }
    }

    public function test_cancel_reads_the_version_first_and_sends_it_in_if_match(): void
    {
        $gateway = $this->gateway(
            new MockResponse(json_encode(['status' => 'open', 'version' => 3], \JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode(['status' => 'cancelled', 'version' => 4], \JSON_THROW_ON_ERROR)),
        );

        $gateway->cancelRequest('sub-1', 'pr-1', 'amount changed');

        self::assertSame('GET', $this->sent[0]['method']);
        self::assertSame('POST', $this->sent[1]['method']);
        self::assertStringEndsWith('/payment-requests/pr-1/cancel', $this->sent[1]['url']);
        self::assertContains('If-Match: "3"', $this->headers(1));
    }

    public function test_cancelling_a_cancelled_request_does_nothing(): void
    {
        $gateway = $this->gateway(new MockResponse(json_encode(['status' => 'cancelled', 'version' => 2], \JSON_THROW_ON_ERROR)));

        $gateway->cancelRequest('sub-1', 'pr-1', 'again');

        self::assertCount(1, $this->sent);
    }

    public function test_a_stale_version_is_reread_once(): void
    {
        $gateway = $this->gateway(
            new MockResponse(json_encode(['status' => 'open', 'version' => 1], \JSON_THROW_ON_ERROR)),
            $this->error(412, 'precondition_failed'),
            new MockResponse(json_encode(['status' => 'open', 'version' => 2], \JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode(['status' => 'cancelled'], \JSON_THROW_ON_ERROR)),
        );

        $gateway->cancelRequest('sub-1', 'pr-1', 'x');

        self::assertContains('If-Match: "2"', $this->headers(3));
    }

    public function test_cancelling_an_allocated_request_is_reported(): void
    {
        $gateway = $this->gateway(
            new MockResponse(json_encode(['status' => 'partially_paid', 'version' => 1], \JSON_THROW_ON_ERROR)),
            $this->error(409, 'payment_request_has_allocations'),
        );

        $this->expectException(PaymentRequestHasAllocations::class);
        $gateway->cancelRequest('sub-1', 'pr-1', 'x');
    }

    public function test_an_account_connected_elsewhere_is_a_domain_exception(): void
    {
        $gateway = $this->gateway($this->error(409, 'account_unavailable'));

        $this->expectException(BankAccountUnavailable::class);
        $gateway->connectBankAccount('sub-1', '19-2000145399/0800', 'Main');
    }

    public function test_an_account_number_and_an_iban_are_sent_in_their_own_fields(): void
    {
        $answer = json_encode(['id' => 'bc-1', 'iban' => 'CZ6508000000192000145399', 'inbound_address' => null], \JSON_THROW_ON_ERROR);
        $gateway = $this->gateway(new MockResponse($answer, ['http_code' => 201]), new MockResponse($answer, ['http_code' => 201]));

        $gateway->connectBankAccount('sub-1', '19-2000145399/0800', 'Main');
        $gateway->connectBankAccount('sub-1', 'CZ65 0800 0000 1920 0014 5399', 'Main');

        self::assertSame('19-2000145399/0800', $this->body(0)['account_number']);
        self::assertSame('CZ6508000000192000145399', $this->body(1)['iban']);
    }

    public function test_an_existing_subtenant_is_found_after_a_duplicate_error(): void
    {
        $gateway = $this->gateway(
            $this->error(409, 'duplicate_external_id'),
            new MockResponse(json_encode(['items' => [['id' => 'sub-9', 'external_id' => 'ext-9']]], \JSON_THROW_ON_ERROR)),
        );

        self::assertSame('sub-9', $gateway->createSubtenant('ext-9', 'Club', 'c@example.test')->id);
    }

    public function test_the_event_log_returns_the_webhook_envelopes_and_the_cursor(): void
    {
        $gateway = $this->gateway(new MockResponse(json_encode([
            'items' => [['id' => 'e1', 'body' => ['id' => 'e1', 'type' => 'payment_request.updated']], ['id' => 'e2', 'body' => ['id' => 'e2', 'type' => 'transaction.recorded']]],
            'next_cursor' => 'abc',
        ], \JSON_THROW_ON_ERROR)));

        $page = $gateway->listWebhookEvents(new \DateTimeImmutable('2026-11-01T10:00:00+01:00'), null, 50);

        self::assertSame(['e1', 'e2'], array_column($page->events, 'id'));
        self::assertSame('abc', $page->nextCursor);
        self::assertStringContainsString('from=2026-11-01T09:00:00Z', $this->sent[0]['url']);
    }

    public function test_an_allocation_carries_the_idempotency_key_and_both_versions(): void
    {
        $gateway = $this->gateway(
            new MockResponse(json_encode(['version' => 5], \JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode(['version' => 7], \JSON_THROW_ON_ERROR)),
            new MockResponse('{}', ['http_code' => 201]),
        );

        $gateway->allocate(new AllocationRequest('sub-1', 'tx-1', 'pr-1', 'alloc_tx-1_pr-1', 'wrong symbol'));

        self::assertContains('Idempotency-Key: alloc_tx-1_pr-1', $this->headers(2));
        $body = $this->body(2);
        self::assertSame(['payment_request_id' => 'pr-1', 'expected_transaction_version' => 5, 'expected_request_version' => 7, 'reason' => 'wrong symbol'], $body);
    }

    public function test_reception_states_are_mapped(): void
    {
        foreach ([['address_not_created', 'active', ReceptionState::NotConfigured], ['waiting_for_first_message', 'active', ReceptionState::Waiting], ['bank_message_trusted', 'active', ReceptionState::Active], ['bank_message_trusted', 'paused_foreign_mail', ReceptionState::Paused]] as [$setup, $processing, $expected]) {
            $gateway = $this->gateway(new MockResponse(json_encode(['setup_status' => $setup, 'address_processing' => $processing], \JSON_THROW_ON_ERROR)));

            self::assertSame($expected, $gateway->receptionState('sub-1', 'bc-1'));
        }
    }

    /**
     * @return list<string>
     */
    private function headers(int $request): array
    {
        $headers = $this->sent[$request]['options']['headers'] ?? [];

        return array_values(array_filter(\is_array($headers) ? $headers : [], \is_string(...)));
    }

    /**
     * @return array<string, mixed>
     */
    private function body(int $request): array
    {
        $body = $this->sent[$request]['options']['body'] ?? '';
        $decoded = json_decode(\is_string($body) ? $body : '', true);

        /** @var array<string, mixed> $result */
        $result = \is_array($decoded) ? $decoded : [];

        return $result;
    }

    private function spec(): PaymentRequestSpec
    {
        return new PaymentRequestSpec('sub-1', 'bc-1', 'order_a_1', Money::of(45_000), 'EV-2026-000123', 'Order a', new \DateTimeImmutable('2026-11-15T18:00:00+01:00'));
    }

    private function error(int $status, string $code): MockResponse
    {
        return new MockResponse(json_encode(['error' => ['code' => $code, 'message' => 'x']], \JSON_THROW_ON_ERROR), ['http_code' => $status]);
    }

    private function gateway(MockResponse|\Throwable ...$answers): HttpPairrotGateway
    {
        $queue = $answers;
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$queue): MockResponse {
            $this->sent[] = ['method' => $method, 'url' => $url, 'options' => $options];
            $next = array_shift($queue) ?? throw new \LogicException('Unexpected extra request '.$method.' '.$url);
            if ($next instanceof \Throwable) {
                throw $next;
            }

            return $next;
        }, 'http://pairrot.test');

        return new HttpPairrotGateway($client);
    }
}
