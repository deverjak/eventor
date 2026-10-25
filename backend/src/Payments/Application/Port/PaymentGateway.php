<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Port;

use Eventor\Payments\Application\Port\Exception\PaymentGatewayException;
use Eventor\Payments\Application\Port\Exception\PaymentGatewayRejected;
use Eventor\Payments\Application\Port\Exception\PaymentGatewayUnavailable;
use Eventor\Payments\Application\Port\Exception\PaymentRequestConflict;
use Eventor\Payments\Application\Port\Exception\PaymentRequestHasAllocations;
use Eventor\Payments\Contract\Exception\BankAccountUnavailable;
use Eventor\Payments\Contract\ReceptionState;

/**
 * Port to the bank-payment service (Pairrot, chapter 07). It speaks the language of the domain; the wire format
 * (snake_case, minor units, ETags) is known to the adapter only. Every method is safe to repeat: creation is
 * idempotent by `externalId`, cancellation of a cancelled request is a no-op, an allocation carries an Idempotency-Key,
 * a subtenant is looked up before it is created.
 *
 * @throws PaymentGatewayUnavailable network, 5xx, 429, open circuit: retry later
 * @throws PaymentGatewayRejected    any other 4xx: do not retry
 */
interface PaymentGateway
{
    /**
     * 201 and 200 `replayed` both return the request.
     *
     * @throws PaymentRequestConflict the same `externalId` exists with another body
     * @throws PaymentGatewayException
     */
    public function createRequest(PaymentRequestSpec $spec): IssuedPaymentRequest;

    /**
     * @throws PaymentRequestHasAllocations money arrived meanwhile
     * @throws PaymentGatewayException
     */
    public function cancelRequest(string $subtenantId, string $pairrotRequestId, string $reason): void;

    /**
     * @throws PaymentGatewayException
     */
    public function getRequest(string $subtenantId, string $pairrotRequestId): PairrotRequestSnapshot;

    /**
     * Webhook events of the root and its children, oldest first within the page (the reconcile job feeds them to the inbox).
     *
     * @throws PaymentGatewayException
     */
    public function listWebhookEvents(\DateTimeImmutable $from, ?string $cursor = null, int $limit = 100): WebhookEventPage;

    /**
     * Allocates a whole bank transaction to a payment request (PAY-06). The ledger changes only when the resulting
     * `payment_request.updated` comes back through the inbox.
     *
     * @throws PaymentGatewayException
     */
    public function allocate(AllocationRequest $request): void;

    /**
     * @throws PaymentGatewayException
     */
    public function createSubtenant(string $externalId, string $name, string $contactEmail): PairrotSubtenant;

    /**
     * @throws PaymentGatewayException
     */
    public function findSubtenant(string $externalId): ?PairrotSubtenant;

    /**
     * @throws BankAccountUnavailable the account is connected to another tenant
     * @throws PaymentGatewayException
     */
    public function connectBankAccount(string $subtenantId, string $accountNumberOrIban, string $label): PairrotBankConnection;

    /**
     * @return string the inbound address for the forwarded bank notifications
     *
     * @throws PaymentGatewayException
     */
    public function createInboundAddress(string $subtenantId, string $bankConnectionId): string;

    /**
     * @throws PaymentGatewayException
     */
    public function receptionState(string $subtenantId, string $bankConnectionId): ReceptionState;
}
