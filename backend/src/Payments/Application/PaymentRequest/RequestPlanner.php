<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\PaymentRequest;

use Eventor\Payments\Contract\PaymentRequestPurpose;
use Eventor\Shared\Domain\Money;

/**
 * "Změna dlužné částky" of chapter 07 as a pure function. The amount of a request in Pairrot never changes, so when
 * the amount due changes the planner decides which active requests to cancel (only those without allocations: Pairrot
 * refuses the others) and whether a new one is needed, for exactly what is still missing:
 *
 *     need   = amountDue - max(received, 0)
 *     P      = what the active requests that already got money still expect (they stay open)
 *     wanted = need - P
 *
 * Nothing wanted -> cancel the unpaid requests. Otherwise keep an unpaid request of exactly `wanted`, else cancel the
 * unpaid ones and issue a new request for `wanted` (`retry` when no money arrived yet, `supplementary` when some did).
 * The plan is computed from the current state, so repeating it after a crash converges instead of duplicating.
 */
final class RequestPlanner
{
    /**
     * @param list<RequestFacts> $requests
     */
    public static function plan(Money $amountDue, Money $received, array $requests): RequestPlan
    {
        $zero = Money::zero($amountDue->currency);
        $active = array_values(array_filter($requests, static fn(RequestFacts $r): bool => $r->active));
        $unpaid = array_values(array_filter($active, static fn(RequestFacts $r): bool => $r->knownPaid->isZero()));

        $covered = $zero;
        foreach ($active as $request) {
            if (!$request->knownPaid->isZero()) {
                $covered = $covered->add($request->outstanding());
            }
        }

        $held = $received->isPositive() ? $received : $zero;
        $wanted = $amountDue->subtract($held)->subtract($covered);

        if (!$wanted->isPositive()) {
            return new RequestPlan(array_map(static fn(RequestFacts $r) => $r->id, $unpaid), null, null);
        }

        $cancel = [];
        $keep = false;
        foreach ($unpaid as $request) {
            if (!$keep && $request->amount->equals($wanted)) {
                $keep = true;
                continue;
            }
            $cancel[] = $request->id;
        }
        if ($keep) {
            return new RequestPlan($cancel, null, null);
        }

        $purpose = $held->isZero() && $covered->isZero() ? PaymentRequestPurpose::Retry : PaymentRequestPurpose::Supplementary;

        return new RequestPlan($cancel, $wanted, $purpose);
    }
}
