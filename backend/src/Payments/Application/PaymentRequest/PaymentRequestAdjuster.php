<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\PaymentRequest;

use Eventor\Payments\Application\ChangeOrderAmountDue\ChangeOrderAmountDue;
use Eventor\Payments\Application\Port\Exception\PaymentRequestHasAllocations;
use Eventor\Payments\Application\Port\OrganizerPaymentPolicy;
use Eventor\Payments\Application\Port\PaymentGateway;
use Eventor\Payments\Domain\Request\CreationState;
use Eventor\Payments\Domain\Request\PaymentRequest;
use Eventor\Payments\Domain\Request\PaymentRequestRepository;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\PaymentRequestId;
use Eventor\Shared\Domain\Money;
use Psr\Clock\ClockInterface;

/**
 * `registration.order_amount_changed` (S18, REG-14, event cancellation): recompute the financial state, then bring the
 * requests in Pairrot in line with the new amount (chapter 07, "Změna dlužné částky"). Every step is idempotent and
 * the plan is derived from the current state, so a redelivery after a crash finishes the job instead of repeating it.
 */
final readonly class PaymentRequestAdjuster
{
    public function __construct(
        private CommandBus $commands,
        private PaymentRequestRepository $requests,
        private PaymentGateway $gateway,
        private PaymentRequestIssuer $issuer,
        private OrganizerPaymentPolicy $policy,
        private ClockInterface $clock,
    ) {}

    public function adjust(OrganizerId $organizer, OrderId $order, Money $newAmountDue, string $reason): void
    {
        /** @var array{changed: bool, state: string, received: int, amountDue: int} $finance */
        $finance = $this->commands->dispatch(new ChangeOrderAmountDue($order->toString(), $newAmountDue->minor, $reason));

        $requests = $this->requests->forOrder($order);
        if ([] === $requests) {
            return; // cash or free order, or the first request is still on its way: nothing in Pairrot to adjust
        }

        $facts = array_map(static fn(PaymentRequest $r): RequestFacts => new RequestFacts($r->id(), $r->amount(), $r->knownPaid(), $r->isActive()), $requests);
        $plan = RequestPlanner::plan($newAmountDue, Money::of($finance['received'], $newAmountDue->currency), $facts);
        if ($plan->isEmpty()) {
            return;
        }

        $byId = [];
        foreach ($requests as $request) {
            $byId[$request->id()->toString()] = $request;
        }
        foreach ($plan->cancel as $id) {
            $this->cancel($byId[$id->toString()], $reason);
        }

        if (null !== $plan->issueAmount && null !== $plan->issuePurpose) {
            $this->issueNew($organizer, $order, $requests, $plan->issueAmount, $plan->issuePurpose->value);
        }
    }

    private function cancel(PaymentRequest $request, string $reason): void
    {
        if (CreationState::Created === $request->creationState() && null !== $request->pairrotId()) {
            try {
                $this->gateway->cancelRequest($request->subtenantId(), $request->pairrotId(), $reason);
            } catch (PaymentRequestHasAllocations) {
                return; // money arrived in the meantime; its webhook updates the mirror and the next plan accounts for it
            }
        }

        $this->commands->dispatch(new CancelPaymentRequest($request->id()->toString(), $reason));
    }

    /**
     * @param list<PaymentRequest> $existing
     */
    private function issueNew(OrganizerId $organizer, OrderId $order, array $existing, Money $amount, string $purpose): void
    {
        $latest = array_last($existing) ?? throw new \LogicException('An order with a request to adjust has requests.');
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $dueAt = $latest->dueAt() > $now ? $latest->dueAt() : $now->modify('+'.$this->policy->defaultDueDays($organizer).' days');

        /** @var array{paymentRequestId: string, creationState: string, attempt: int} $registered */
        $registered = $this->commands->dispatch(new RegisterPaymentRequest(
            $order->toString(),
            $this->requests->highestAttempt($order) + 1,
            $purpose,
            $amount->minor,
            $latest->message(),
            $dueAt->format(\DATE_ATOM),
        ));

        $this->issuer->issue(PaymentRequestId::fromString($registered['paymentRequestId']));
    }
}
