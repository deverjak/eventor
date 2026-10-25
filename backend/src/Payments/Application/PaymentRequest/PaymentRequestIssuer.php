<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\PaymentRequest;

use Eventor\Payments\Application\Port\Exception\PaymentGatewayException;
use Eventor\Payments\Application\Port\Exception\PaymentGatewayUnavailable;
use Eventor\Payments\Application\Port\PaymentGateway;
use Eventor\Payments\Application\Port\PaymentRequestSpec;
use Eventor\Payments\Domain\Request\CreationState;
use Eventor\Payments\Domain\Request\PaymentRequestNotFound;
use Eventor\Payments\Domain\Request\PaymentRequestRepository;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Domain\Id\PaymentRequestId;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

/**
 * Calls Pairrot for a PENDING payment request and records the outcome. It runs OUTSIDE any transaction (the callers
 * are the outbox subscribers and the "try again" endpoint): the HTTP call never happens inside a transaction that
 * changes domain state (chapter 07). Safe to repeat: the creation is idempotent by `external_id`.
 *
 * - Pairrot unavailable: the attempt is counted and the exception goes up, so the outbox retries with backoff (S08).
 * - Any other refusal is final: the request becomes FAILED and the message is not retried.
 */
final readonly class PaymentRequestIssuer
{
    public function __construct(
        private PaymentGateway $gateway,
        private PaymentRequestRepository $requests,
        private CommandBus $commands,
    ) {}

    public function issue(PaymentRequestId $id): void
    {
        $request = $this->requests->find($id) ?? throw PaymentRequestNotFound::forId($id->toString());
        if (CreationState::Pending !== $request->creationState()) {
            return;
        }

        $spec = new PaymentRequestSpec(
            $request->subtenantId(),
            $request->bankConnectionId(),
            $request->externalId(),
            $request->amount(),
            $request->message(),
            'Order '.$request->orderId()->toString(),
            $request->dueAt(),
        );

        try {
            $issued = $this->gateway->createRequest($spec);
        } catch (PaymentGatewayUnavailable $e) {
            $this->commands->dispatch(new RecordCreationFailure($id->toString(), $e->errorCode()));

            throw $e;
        } catch (PaymentGatewayException $e) {
            $this->commands->dispatch(new RecordCreationFailure($id->toString(), $e->errorCode(), true));

            throw new UnrecoverableMessageHandlingException($e->getMessage(), 0, $e);
        }

        $this->commands->dispatch(new MarkPaymentRequestCreated(
            $id->toString(),
            $issued->pairrotId,
            $issued->variableSymbol,
            $issued->accountNumber,
            $issued->iban,
            $issued->spaydPayload,
        ));
    }
}
