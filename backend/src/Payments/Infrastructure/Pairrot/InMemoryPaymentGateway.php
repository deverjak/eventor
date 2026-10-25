<?php

declare(strict_types=1);

namespace Eventor\Payments\Infrastructure\Pairrot;

use Eventor\Payments\Application\Port\AllocationRequest;
use Eventor\Payments\Application\Port\Exception\PaymentGatewayRejected;
use Eventor\Payments\Application\Port\Exception\PaymentGatewayUnavailable;
use Eventor\Payments\Application\Port\Exception\PaymentRequestConflict;
use Eventor\Payments\Application\Port\Exception\PaymentRequestHasAllocations;
use Eventor\Payments\Application\Port\IssuedPaymentRequest;
use Eventor\Payments\Application\Port\PairrotBankConnection;
use Eventor\Payments\Application\Port\PairrotRequestSnapshot;
use Eventor\Payments\Application\Port\PairrotSubtenant;
use Eventor\Payments\Application\Port\PaymentGateway;
use Eventor\Payments\Application\Port\PaymentRequestSpec;
use Eventor\Payments\Application\Port\WebhookEventPage;
use Eventor\Payments\Application\Webhook\PairrotWebhookSignature;
use Eventor\Payments\Contract\Exception\BankAccountUnavailable;
use Eventor\Payments\Contract\ReceptionState;
use Eventor\Payments\Infrastructure\Pairrot\Webhook\LocalWebhookSecret;
use Eventor\Shared\Domain\Currency;
use Eventor\Shared\Domain\Money;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Uid\Uuid;

/**
 * Fake of Pairrot for the `test` environment (ADR 004: "an in-memory fake exists only in test"). It behaves like the
 * real API where Eventor depends on it: `external_id` idempotency (201 / replay / conflict), a VS assigned by
 * "Pairrot", cancellation that refuses allocated requests, subtenant and bank-connection idempotency, an event log
 * with the same envelope as the webhooks. Tests drive the outside world with `receivePayment()`, `revoke()`,
 * `outage()`, `failNext()` and `signWebhook()`; the decorators stay in front of it (priority chain unchanged).
 * One instance per container: state lives as long as the kernel.
 *
 * @phpstan-type Req array{id: string, externalId: string, subtenant: string, connection: string, amount: int, vs: string, iban: string, dueAt: \DateTimeImmutable, message: string, paid: int, sequence: int, cancelled: bool, fingerprint: list<int|string>}
 */
final class InMemoryPaymentGateway implements PaymentGateway
{
    /** @var array<string, Req> external id => request record */
    private array $requests = [];
    /** @var array<string, array{id: string, externalId: string}> */
    private array $subtenants = [];
    /** @var array<string, array{id: string, subtenant: string, iban: string, inbound: string|null}> account => connection */
    private array $connections = [];
    /** @var list<array<string, mixed>> */
    private array $events = [];
    /** @var array<string, array{id: string, amount: int, subtenant: string, allocatedTo: string|null}> */
    private array $transactions = [];
    /** @var list<array{operation: string, context: array<string, scalar|null>}> */
    private array $calls = [];
    private int $vs = 0;
    private int $failures = 0;
    private bool $outage = false;
    private ?ReceptionState $reception = null;
    private \DateTimeImmutable $clockNow;

    public function __construct()
    {
        $this->clockNow = \DateTimeImmutable::createFromInterface(Clock::get()->now());
    }

    // ---------------------------------------------------------------- port

    public function createRequest(PaymentRequestSpec $spec): IssuedPaymentRequest
    {
        $this->enter('create_request', ['external_id' => $spec->externalId]);

        $fingerprint = [$spec->subtenantId, $spec->bankConnectionId, $spec->amount->minor, $spec->message, $spec->description, $spec->dueAt->getTimestamp()];
        $existing = $this->requests[$spec->externalId] ?? null;
        if (null !== $existing) {
            if ($existing['fingerprint'] !== $fingerprint) {
                throw new PaymentRequestConflict('external_id_conflict');
            }

            return $this->issued($existing, true);
        }

        $connection = $this->connectionById($spec->bankConnectionId);
        $record = [
            'id' => Uuid::v7()->toRfc4122(),
            'externalId' => $spec->externalId,
            'subtenant' => $spec->subtenantId,
            'connection' => $spec->bankConnectionId,
            'amount' => $spec->amount->minor,
            'vs' => \sprintf('2026%06d', ++$this->vs),
            'iban' => $connection['iban'],
            'dueAt' => $spec->dueAt,
            'message' => $spec->message,
            'paid' => 0,
            'sequence' => 0,
            'cancelled' => false,
            'fingerprint' => $fingerprint,
        ];
        $this->requests[$spec->externalId] = $record;

        return $this->issued($record, false);
    }

    public function cancelRequest(string $subtenantId, string $pairrotRequestId, string $reason): void
    {
        $this->enter('cancel_request', ['request_id' => $pairrotRequestId]);
        $key = $this->keyOf($pairrotRequestId);
        if ($this->requests[$key]['cancelled']) {
            return;
        }
        if ($this->requests[$key]['paid'] > 0) {
            throw new PaymentRequestHasAllocations();
        }

        $record = $this->requests[$key];
        $previous = $this->state($record);
        $record['cancelled'] = true;
        $record['sequence'] = $record['sequence'] + 1;
        $this->requests[$key] = $record;
        $this->emit($record, 'request_cancelled', $previous);
    }

    public function getRequest(string $subtenantId, string $pairrotRequestId): PairrotRequestSnapshot
    {
        $this->enter('get_request', ['request_id' => $pairrotRequestId]);
        $record = $this->requests[$this->keyOf($pairrotRequestId)];

        return new PairrotRequestSnapshot($pairrotRequestId, $record['externalId'], $this->state($record)['status'], $record['paid'], $record['sequence']);
    }

    public function listWebhookEvents(\DateTimeImmutable $from, ?string $cursor = null, int $limit = 100): WebhookEventPage
    {
        $this->enter('list_webhook_events', ['limit' => $limit]);
        $matching = array_values(array_filter(
            $this->events,
            static fn(array $event): bool => new \DateTimeImmutable(\is_string($event['created_at'] ?? null) ? $event['created_at'] : '@0') >= $from,
        ));
        $offset = null === $cursor ? 0 : (int) $cursor;
        $page = \array_slice($matching, $offset, $limit);
        $next = $offset + $limit < \count($matching) ? (string) ($offset + $limit) : null;

        return new WebhookEventPage($page, $next);
    }

    public function allocate(AllocationRequest $request): void
    {
        $this->enter('allocate', ['transaction_id' => $request->pairrotTransactionId, 'request_id' => $request->pairrotRequestId]);
        $transaction = $this->transactions[$request->pairrotTransactionId] ?? throw new PaymentGatewayRejected(404, 'not_found');
        if (null !== $transaction['allocatedTo']) {
            if ($transaction['allocatedTo'] === $request->pairrotRequestId) {
                return; // same Idempotency-Key and input: the stored answer
            }
            throw new PaymentGatewayRejected(409, 'transaction_already_allocated');
        }

        $key = $this->keyOf($request->pairrotRequestId);
        $this->transactions[$request->pairrotTransactionId]['allocatedTo'] = $request->pairrotRequestId;
        $this->pay($key, $transaction['amount'], 'allocation_created');
        $this->record('transaction.updated', $transaction['subtenant'], [
            'subject_id' => $transaction['id'],
            'sequence' => 2,
            'cause' => 'allocated',
            'transaction_id' => $transaction['id'],
            'amount' => $transaction['amount'],
            'currency' => 'CZK',
            'handling_state' => 'allocated',
            'matching' => ['result' => 'amount_mismatch', 'payment_request_id' => $request->pairrotRequestId, 'external_id' => $this->requests[$key]['externalId']],
        ]);
    }

    public function createSubtenant(string $externalId, string $name, string $contactEmail): PairrotSubtenant
    {
        $this->enter('create_subtenant', ['external_id' => $externalId]);
        $this->subtenants[$externalId] ??= ['id' => Uuid::v7()->toRfc4122(), 'externalId' => $externalId];

        return new PairrotSubtenant($this->subtenants[$externalId]['id'], $externalId);
    }

    public function findSubtenant(string $externalId): ?PairrotSubtenant
    {
        $this->enter('find_subtenant', ['external_id' => $externalId]);

        return isset($this->subtenants[$externalId]) ? new PairrotSubtenant($this->subtenants[$externalId]['id'], $externalId) : null;
    }

    public function connectBankAccount(string $subtenantId, string $accountNumberOrIban, string $label): PairrotBankConnection
    {
        $this->enter('connect_bank_account', ['subtenant_id' => $subtenantId]);
        $account = preg_replace('/\s+/', '', $accountNumberOrIban) ?? $accountNumberOrIban;
        $existing = $this->connections[$account] ?? null;
        if (null !== $existing) {
            if ($existing['subtenant'] !== $subtenantId) {
                throw BankAccountUnavailable::alreadyConnected();
            }

            return new PairrotBankConnection($existing['id'], $existing['iban'], $existing['inbound']);
        }

        $this->connections[$account] = ['id' => Uuid::v7()->toRfc4122(), 'subtenant' => $subtenantId, 'iban' => $account, 'inbound' => null];

        return new PairrotBankConnection($this->connections[$account]['id'], $account, null);
    }

    public function createInboundAddress(string $subtenantId, string $bankConnectionId): string
    {
        $this->enter('create_inbound_address', ['connection_id' => $bankConnectionId]);
        foreach ($this->connections as $account => $connection) {
            if ($connection['id'] === $bankConnectionId) {
                $this->connections[$account]['inbound'] ??= 'p_'.substr(md5($bankConnectionId), 0, 20).'@in.pairrot.test';

                return (string) $this->connections[$account]['inbound'];
            }
        }

        throw new PaymentGatewayRejected(404, 'not_found');
    }

    public function receptionState(string $subtenantId, string $bankConnectionId): ReceptionState
    {
        $this->enter('reception_state', ['connection_id' => $bankConnectionId]);

        return $this->reception ?? ReceptionState::Waiting;
    }

    // ---------------------------------------------------------------- the outside world (tests)

    /**
     * An incoming transfer allocated by "Pairrot" to the request. Returns the webhook envelope Pairrot would deliver.
     *
     * @return array<string, mixed>
     */
    public function receivePayment(string $pairrotRequestId, int $amountMinor): array
    {
        return $this->pay($this->keyOf($pairrotRequestId), $amountMinor, 'allocation_created');
    }

    /**
     * Pairrot revoked an allocation: the paid amount falls by `$amountMinor`.
     *
     * @return array<string, mixed>
     */
    public function revoke(string $pairrotRequestId, int $amountMinor): array
    {
        return $this->pay($this->keyOf($pairrotRequestId), -$amountMinor, 'allocation_revoked');
    }

    /**
     * A review case removed the allocation of a transaction (`transaction_review_required`).
     *
     * @return array<string, mixed>
     */
    public function reviewRequired(string $pairrotRequestId, int $removedMinor = 0): array
    {
        return $this->pay($this->keyOf($pairrotRequestId), -$removedMinor, 'transaction_review_required');
    }

    /**
     * A bank transaction Pairrot could not match; `transaction.recorded` is added to the event log and returned.
     *
     * @return array<string, mixed>
     */
    public function receiveUnmatchedTransaction(string $subtenantId, int $amountMinor, ?string $variableSymbol, string $matchingResult = 'amount_mismatch', ?string $suggestedRequestId = null): array
    {
        $id = Uuid::v7()->toRfc4122();
        $this->transactions[$id] = ['id' => $id, 'amount' => $amountMinor, 'subtenant' => $subtenantId, 'allocatedTo' => null];

        return $this->record('transaction.recorded', $subtenantId, [
            'subject_id' => $id,
            'sequence' => 1,
            'cause' => 'recorded',
            'transaction_id' => $id,
            'amount' => $amountMinor,
            'currency' => 'CZK',
            'variable_symbol' => $variableSymbol,
            'booked_on' => $this->clockNow->format('Y-m-d'),
            'handling_state' => 'unallocated',
            'matching' => ['result' => $matchingResult, 'payment_request_id' => $suggestedRequestId, 'external_id' => null],
        ]);
    }

    /**
     * @param array<string, mixed> $event
     *
     * @return array{body: string, headers: array<string, string>}
     */
    public function signWebhook(array $event, ?\DateTimeImmutable $at = null, ?string $secret = null): array
    {
        $body = json_encode($event, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
        $timestamp = (string) ($at ?? Clock::get()->now())->getTimestamp();
        $deliveryId = Uuid::v7()->toRfc4122();
        $key = PairrotWebhookSignature::decodeSecret($secret ?? LocalWebhookSecret::SECRET);

        return [
            'body' => $body,
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8',
                'Pairrot-Webhook-Id' => $deliveryId,
                'Pairrot-Webhook-Timestamp' => $timestamp,
                'Pairrot-Webhook-Signature' => PairrotWebhookSignature::header($deliveryId, $timestamp, $body, LocalWebhookSecret::KEY_ID, $key),
            ],
        ];
    }

    /** Every call fails with `PaymentGatewayUnavailable` until `outage(false)` (S08). */
    public function outage(bool $down = true): void
    {
        $this->outage = $down;
    }

    /** The next `$times` calls fail with `PaymentGatewayUnavailable`, then Pairrot recovers. */
    public function failNext(int $times): void
    {
        $this->failures = $times;
    }

    public function setReception(ReceptionState $state): void
    {
        $this->reception = $state;
    }

    public function reset(): void
    {
        $this->requests = $this->subtenants = $this->connections = $this->events = $this->transactions = $this->calls = [];
        $this->vs = $this->failures = 0;
        $this->outage = false;
        $this->reception = null;
    }

    /**
     * Calls that reached the fake (after the decorators), in order.
     *
     * @return list<array{operation: string, context: array<string, scalar|null>}>
     */
    public function calls(): array
    {
        return $this->calls;
    }

    public function countCalls(string $operation): int
    {
        return \count(array_filter($this->calls, static fn(array $call): bool => $call['operation'] === $operation));
    }

    /**
     * @return array{id: string, externalId: string, amount: int, paid: int, cancelled: bool, vs: string}|null
     */
    public function requestByExternalId(string $externalId): ?array
    {
        $record = $this->requests[$externalId] ?? null;

        return null === $record ? null : ['id' => $record['id'], 'externalId' => $externalId, 'amount' => $record['amount'], 'paid' => $record['paid'], 'cancelled' => $record['cancelled'], 'vs' => $record['vs']];
    }

    /** @return list<array<string, mixed>> */
    public function events(): array
    {
        return $this->events;
    }

    // ---------------------------------------------------------------- internals

    /**
     * @param array<string, scalar|null> $context
     */
    private function enter(string $operation, array $context): void
    {
        $this->calls[] = ['operation' => $operation, 'context' => $context];
        if ($this->outage || $this->failures > 0) {
            if ($this->failures > 0) {
                --$this->failures;
            }
            throw new PaymentGatewayUnavailable('Pairrot (fake) is down.', 'pairrot_transport_error');
        }
    }

    private function keyOf(string $pairrotRequestId): string
    {
        foreach ($this->requests as $key => $record) {
            if ($record['id'] === $pairrotRequestId) {
                return $key;
            }
        }

        throw new PaymentGatewayRejected(404, 'not_found');
    }

    /**
     * @return array{id: string, subtenant: string, iban: string, inbound: string|null}
     */
    private function connectionById(string $id): array
    {
        foreach ($this->connections as $connection) {
            if ($connection['id'] === $id) {
                return $connection;
            }
        }

        throw new PaymentGatewayRejected(404, 'not_found');
    }

    /**
     * @param Req $record
     */
    private function issued(array $record, bool $replayed): IssuedPaymentRequest
    {
        return new IssuedPaymentRequest(
            $record['id'],
            $record['externalId'],
            $record['vs'],
            null,
            $record['iban'],
            Money::of($record['amount'], Currency::CZK),
            $record['dueAt'],
            \sprintf('SPD*1.0*ACC:%s*AM:%s*CC:CZK*X-VS:%s*MSG:%s', $record['iban'], number_format($record['amount'] / 100, 2, '.', ''), $record['vs'], $record['message']),
            $replayed,
        );
    }

    /**
     * @param Req $record
     *
     * @return array{status: string, paid: int, remaining: int|null, overpaid: int}
     */
    private function state(array $record): array
    {
        $paid = (int) $record['paid'];
        $amount = (int) $record['amount'];
        if ($record['cancelled']) {
            return ['status' => 'cancelled', 'paid' => 0, 'remaining' => null, 'overpaid' => 0];
        }

        return [
            'status' => 0 === $paid ? 'open' : ($paid < $amount ? 'partially_paid' : ($paid === $amount ? 'paid' : 'overpaid')),
            'paid' => $paid,
            'remaining' => max(0, $amount - $paid),
            'overpaid' => max(0, $paid - $amount),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pay(string $key, int $deltaMinor, string $cause): array
    {
        $record = $this->requests[$key];
        $previous = $this->state($record);
        $record['paid'] = max(0, $record['paid'] + $deltaMinor);
        $record['sequence'] = $record['sequence'] + 1;
        $this->requests[$key] = $record;

        return $this->emit($record, $cause, $previous);
    }

    /**
     * @param Req $record
     * @param array{status: string, paid: int, remaining: int|null, overpaid: int} $previous
     *
     * @return array<string, mixed>
     */
    private function emit(array $record, string $cause, array $previous): array
    {
        $current = $this->state($record);

        return $this->record('payment_request.updated', $record['subtenant'], [
            'payment_request_id' => $record['id'],
            'external_id' => $record['externalId'],
            'bank_connection_id' => $record['connection'],
            'sequence' => $record['sequence'],
            'cause' => $cause,
            'previous' => [
                'status' => $previous['status'],
                'paid_amount' => $previous['paid'],
                'remaining_amount' => $previous['remaining'],
                'overpaid_amount' => $previous['overpaid'],
            ],
            'current' => [
                'status' => $current['status'],
                'paid_amount' => $current['paid'],
                'remaining_amount' => $current['remaining'],
                'overpaid_amount' => $current['overpaid'],
            ],
            'expected_amount' => $record['amount'],
            'currency' => 'CZK',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function record(string $type, string $tenantId, array $data): array
    {
        $event = [
            'id' => Uuid::v7()->toRfc4122(),
            'type' => $type,
            'schema_version' => 1,
            'created_at' => \DateTimeImmutable::createFromInterface(Clock::get()->now())->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z'),
            'environment' => 'live',
            'tenant_id' => $tenantId,
            'data' => $data,
        ];
        $this->events[] = $event;

        return $event;
    }
}
