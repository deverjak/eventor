<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Unmatched;

use Eventor\Payments\Application\PaymentRequest\PaymentRequestIssuer;
use Eventor\Payments\Application\PaymentRequest\RegisterPaymentRequest;
use Eventor\Payments\Application\Port\AllocationRequest;
use Eventor\Payments\Application\Port\Exception\PaymentGatewayRejected;
use Eventor\Payments\Application\Port\OrganizerPaymentPolicy;
use Eventor\Payments\Application\Port\PaymentGateway;
use Eventor\Payments\Contract\PaymentRequestPurpose;
use Eventor\Payments\Domain\Request\PaymentRequestRepository;
use Eventor\Payments\Domain\Unmatched\AllocationNotPossible;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Domain\Id\BankTransactionId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\PaymentRequestId;
use Psr\Clock\ClockInterface;

/**
 * "Přiřadit" an unmatched transaction to an order (PAY-06, chapter 07). The ledger is NOT written here: Pairrot is
 * asked to allocate (`Idempotency-Key = alloc_<transactionId>_<paymentRequestId>`), and the resulting
 * `payment_request.updated` comes back through the inbox like any payment. If the order has no open request (it was
 * cancelled), a supplementary request for the amount of the transaction is issued first.
 */
final readonly class UnmatchedAllocator
{
    public function __construct(
        private CommandBus $commands,
        private PaymentGateway $gateway,
        private PaymentRequestIssuer $issuer,
        private PaymentRequestRepository $requests,
        private OrganizerPaymentPolicy $policy,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{paymentRequestId: string}
     */
    public function allocate(OrganizerId $organizer, BankTransactionId $transaction, OrderId $order, ?PaymentRequestId $paymentRequest, string $reason): array
    {
        $decision = $this->decide($transaction, $order, $paymentRequest, $reason);

        if ($decision['needsRequest']) {
            $existing = $this->requests->forOrder($order);
            $latest = array_last($existing) ?? throw AllocationNotPossible::noSuchRequest();
            $now = \DateTimeImmutable::createFromInterface($this->clock->now());
            /** @var array{paymentRequestId: string, creationState: string, attempt: int} $registered */
            $registered = $this->commands->dispatch(new RegisterPaymentRequest(
                $order->toString(),
                $this->requests->highestAttempt($order) + 1,
                PaymentRequestPurpose::Supplementary->value,
                $decision['amountMinor'],
                $latest->message(),
                $now->modify('+'.$this->policy->defaultDueDays($organizer).' days')->format(\DATE_ATOM),
            ));
            $this->issuer->issue(PaymentRequestId::fromString($registered['paymentRequestId']));
            $decision = $this->decide($transaction, $order, PaymentRequestId::fromString($registered['paymentRequestId']), $reason);
        }

        \assert(null !== $decision['subtenantId'] && null !== $decision['pairrotRequestId'] && null !== $decision['paymentRequestId']);

        try {
            $this->gateway->allocate(new AllocationRequest(
                $decision['subtenantId'],
                $decision['pairrotTransactionId'],
                $decision['pairrotRequestId'],
                \sprintf('alloc_%s_%s', $transaction->toString(), $decision['paymentRequestId']),
                $reason,
            ));
        } catch (PaymentGatewayRejected $e) {
            throw AllocationNotPossible::refusedByPairrot($e->errorCode());
        }

        return ['paymentRequestId' => $decision['paymentRequestId']];
    }

    /**
     * @return array{pairrotTransactionId: string, subtenantId: string|null, pairrotRequestId: string|null, paymentRequestId: string|null, needsRequest: bool, amountMinor: int}
     */
    private function decide(BankTransactionId $transaction, OrderId $order, ?PaymentRequestId $paymentRequest, string $reason): array
    {
        /** @var array{pairrotTransactionId: string, subtenantId: string|null, pairrotRequestId: string|null, paymentRequestId: string|null, needsRequest: bool, amountMinor: int} $decision */
        $decision = $this->commands->dispatch(new RequestAllocation($transaction->toString(), $order->toString(), $reason, $paymentRequest?->toString()));

        return $decision;
    }
}
