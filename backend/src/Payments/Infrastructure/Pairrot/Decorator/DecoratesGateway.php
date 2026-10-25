<?php

declare(strict_types=1);

namespace Eventor\Payments\Infrastructure\Pairrot\Decorator;

use Eventor\Payments\Application\Port\AllocationRequest;
use Eventor\Payments\Application\Port\IssuedPaymentRequest;
use Eventor\Payments\Application\Port\PairrotBankConnection;
use Eventor\Payments\Application\Port\PairrotRequestSnapshot;
use Eventor\Payments\Application\Port\PairrotSubtenant;
use Eventor\Payments\Application\Port\PaymentGateway;
use Eventor\Payments\Application\Port\PaymentRequestSpec;
use Eventor\Payments\Application\Port\WebhookEventPage;
use Eventor\Payments\Contract\ReceptionState;

/**
 * Plumbing of the decorator chain: every port method funnels through `call($operation, $context, $fn)`, so a decorator
 * implements its behaviour once instead of eleven times. `$context` is log-safe (operation, external id, no IBAN).
 */
trait DecoratesGateway
{
    /**
     * @template T
     *
     * @param array<string, scalar|null> $context
     * @param callable(PaymentGateway): T $call
     *
     * @return T
     */
    abstract protected function call(string $operation, array $context, callable $call): mixed;

    abstract protected function inner(): PaymentGateway;

    public function createRequest(PaymentRequestSpec $spec): IssuedPaymentRequest
    {
        return $this->call('create_request', ['external_id' => $spec->externalId], static fn(PaymentGateway $g): IssuedPaymentRequest => $g->createRequest($spec));
    }

    public function cancelRequest(string $subtenantId, string $pairrotRequestId, string $reason): void
    {
        $this->call('cancel_request', ['request_id' => $pairrotRequestId], static fn(PaymentGateway $g) => $g->cancelRequest($subtenantId, $pairrotRequestId, $reason));
    }

    public function getRequest(string $subtenantId, string $pairrotRequestId): PairrotRequestSnapshot
    {
        return $this->call('get_request', ['request_id' => $pairrotRequestId], static fn(PaymentGateway $g): PairrotRequestSnapshot => $g->getRequest($subtenantId, $pairrotRequestId));
    }

    public function listWebhookEvents(\DateTimeImmutable $from, ?string $cursor = null, int $limit = 100): WebhookEventPage
    {
        return $this->call('list_webhook_events', ['limit' => $limit], static fn(PaymentGateway $g): WebhookEventPage => $g->listWebhookEvents($from, $cursor, $limit));
    }

    public function allocate(AllocationRequest $request): void
    {
        $this->call('allocate', ['transaction_id' => $request->pairrotTransactionId, 'request_id' => $request->pairrotRequestId], static fn(PaymentGateway $g) => $g->allocate($request));
    }

    public function createSubtenant(string $externalId, string $name, string $contactEmail): PairrotSubtenant
    {
        return $this->call('create_subtenant', ['external_id' => $externalId], static fn(PaymentGateway $g): PairrotSubtenant => $g->createSubtenant($externalId, $name, $contactEmail));
    }

    public function findSubtenant(string $externalId): ?PairrotSubtenant
    {
        return $this->call('find_subtenant', ['external_id' => $externalId], static fn(PaymentGateway $g): ?PairrotSubtenant => $g->findSubtenant($externalId));
    }

    public function connectBankAccount(string $subtenantId, string $accountNumberOrIban, string $label): PairrotBankConnection
    {
        return $this->call('connect_bank_account', ['subtenant_id' => $subtenantId], static fn(PaymentGateway $g): PairrotBankConnection => $g->connectBankAccount($subtenantId, $accountNumberOrIban, $label));
    }

    public function createInboundAddress(string $subtenantId, string $bankConnectionId): string
    {
        return $this->call('create_inbound_address', ['connection_id' => $bankConnectionId], static fn(PaymentGateway $g): string => $g->createInboundAddress($subtenantId, $bankConnectionId));
    }

    public function receptionState(string $subtenantId, string $bankConnectionId): ReceptionState
    {
        return $this->call('reception_state', ['connection_id' => $bankConnectionId], static fn(PaymentGateway $g): ReceptionState => $g->receptionState($subtenantId, $bankConnectionId));
    }
}
