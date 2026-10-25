<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Unmatched;

use Eventor\Payments\Domain\Request\CreationState;
use Eventor\Payments\Domain\Request\PaymentRequest;
use Eventor\Payments\Domain\Request\PaymentRequestRepository;
use Eventor\Payments\Domain\Unmatched\AllocationNotPossible;
use Eventor\Payments\Domain\Unmatched\UnmatchedTransaction;
use Eventor\Payments\Domain\Unmatched\UnmatchedTransactionNotFound;
use Eventor\Payments\Domain\Unmatched\UnmatchedTransactionRepository;
use Eventor\Shared\Application\Audit\AuditContext;
use Eventor\Shared\Domain\Audit\AuditActor;
use Eventor\Shared\Domain\Id\BankTransactionId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\PaymentRequestId;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('When the order has no request that can take the transaction, nothing is decided yet; the decision records AllocationRequested.')]
final readonly class RequestAllocationHandler
{
    public function __construct(
        private UnmatchedTransactionRepository $transactions,
        private PaymentRequestRepository $requests,
        private AuditContext $audit,
    ) {}

    /**
     * @return array{pairrotTransactionId: string, subtenantId: string|null, pairrotRequestId: string|null, paymentRequestId: string|null, needsRequest: bool, amountMinor: int}
     */
    public function __invoke(RequestAllocation $command): array
    {
        $transaction = $this->transactions->find(BankTransactionId::fromString($command->transactionId)) ?? throw UnmatchedTransactionNotFound::forId($command->transactionId);
        if (!$transaction->isOpen()) {
            throw AllocationNotPossible::alreadyResolved();
        }

        $order = OrderId::fromString($command->orderId);
        $candidates = array_values(array_filter(
            $this->requests->forOrder($order),
            static fn(PaymentRequest $r): bool => $r->isActive() && CreationState::Created === $r->creationState() && null !== $r->pairrotId(),
        ));
        if (null !== $command->paymentRequestId) {
            $wanted = PaymentRequestId::fromString($command->paymentRequestId);
            $candidates = array_values(array_filter($candidates, static fn(PaymentRequest $r): bool => $r->id()->equals($wanted)));
            if ([] === $candidates) {
                throw AllocationNotPossible::noSuchRequest();
            }
        }

        if ([] === $this->requests->forOrder($order)) {
            throw AllocationNotPossible::noSuchRequest();
        }

        $chosen = $candidates[0] ?? null;
        if (null === $chosen) {
            // The order's request was cancelled: a supplementary request for the amount of the transaction is issued first.
            return $this->result($transaction, null, true);
        }

        $transaction->allocationRequested($order, $chosen->id(), $command->reason, $this->audit->actor() ?? AuditActor::system('payments'));

        return $this->result($transaction, $chosen, false);
    }

    /**
     * @return array{pairrotTransactionId: string, subtenantId: string|null, pairrotRequestId: string|null, paymentRequestId: string|null, needsRequest: bool, amountMinor: int}
     */
    private function result(UnmatchedTransaction $transaction, ?PaymentRequest $request, bool $needsRequest): array
    {
        return [
            'pairrotTransactionId' => $transaction->pairrotTxId(),
            'subtenantId' => $request?->subtenantId(),
            'pairrotRequestId' => $request?->pairrotId(),
            'paymentRequestId' => $request?->id()->toString(),
            'needsRequest' => $needsRequest,
            'amountMinor' => $transaction->amount()->minor,
        ];
    }
}
