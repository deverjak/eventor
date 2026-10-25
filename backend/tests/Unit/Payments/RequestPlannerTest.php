<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Payments;

use Eventor\Payments\Application\PaymentRequest\RequestFacts;
use Eventor\Payments\Application\PaymentRequest\RequestPlanner;
use Eventor\Payments\Contract\PaymentRequestPurpose;
use Eventor\Shared\Domain\Id\PaymentRequestId;
use Eventor\Shared\Domain\Money;
use PHPUnit\Framework\TestCase;

/**
 * "Změna dlužné částky" (chapter 07, S18): which requests are cancelled and what is issued when the amount due changes.
 */
final class RequestPlannerTest extends TestCase
{
    public function test_no_request_yet_issues_one_for_the_whole_amount(): void
    {
        $plan = RequestPlanner::plan(Money::of(30_000), Money::zero(), []);

        self::assertSame([], $plan->cancel);
        self::assertSame(30_000, $plan->issueAmount?->minor);
        self::assertSame(PaymentRequestPurpose::Retry, $plan->issuePurpose);
    }

    public function test_an_unpaid_request_of_the_right_amount_is_kept(): void
    {
        self::assertTrue(RequestPlanner::plan(Money::of(30_000), Money::zero(), [$this->unpaid(30_000)])->isEmpty());
    }

    public function test_the_debt_rose_before_anything_was_paid_cancel_and_reissue_in_full(): void
    {
        $old = $this->unpaid(30_000);

        $plan = RequestPlanner::plan(Money::of(40_000), Money::zero(), [$old]);

        self::assertEquals([$old->id], $plan->cancel);
        self::assertSame(40_000, $plan->issueAmount?->minor);
        self::assertSame(PaymentRequestPurpose::Retry, $plan->issuePurpose);
    }

    public function test_the_order_was_cancelled_unpaid_requests_are_cancelled_and_nothing_is_issued(): void
    {
        $old = $this->unpaid(30_000);

        $plan = RequestPlanner::plan(Money::zero(), Money::zero(), [$old]);

        self::assertEquals([$old->id], $plan->cancel);
        self::assertNull($plan->issueAmount);
    }

    public function test_the_debt_rose_after_full_payment_issues_a_supplementary_request_for_the_difference(): void
    {
        $paid = $this->request(30_000, 30_000);

        $plan = RequestPlanner::plan(Money::of(40_000), Money::of(30_000), [$paid]);

        self::assertSame([], $plan->cancel, 'a request with allocations is never cancelled');
        self::assertSame(10_000, $plan->issueAmount?->minor);
        self::assertSame(PaymentRequestPurpose::Supplementary, $plan->issuePurpose);
    }

    public function test_the_debt_fell_below_what_was_paid_needs_nothing(): void
    {
        self::assertTrue(RequestPlanner::plan(Money::of(20_000), Money::of(30_000), [$this->request(30_000, 30_000)])->isEmpty());
    }

    public function test_a_partially_paid_request_still_covers_its_remainder_and_repeating_is_harmless(): void
    {
        $partial = $this->request(30_000, 10_000);

        self::assertTrue(RequestPlanner::plan(Money::of(30_000), Money::of(10_000), [$partial])->isEmpty());
    }

    public function test_a_rise_on_a_partially_paid_order_asks_only_for_what_no_request_covers(): void
    {
        $partial = $this->request(30_000, 10_000);

        $plan = RequestPlanner::plan(Money::of(40_000), Money::of(10_000), [$partial]);

        self::assertNull($plan->cancel[0] ?? null);
        self::assertSame(10_000, $plan->issueAmount?->minor);
        self::assertSame(PaymentRequestPurpose::Supplementary, $plan->issuePurpose);
    }

    public function test_an_unpaid_supplementary_request_of_the_right_size_is_kept(): void
    {
        $plan = RequestPlanner::plan(Money::of(40_000), Money::of(30_000), [$this->request(30_000, 30_000), $this->unpaid(10_000)]);

        self::assertTrue($plan->isEmpty());
    }

    public function test_an_unpaid_supplementary_request_of_the_wrong_size_is_replaced(): void
    {
        $wrong = $this->unpaid(10_000);

        $plan = RequestPlanner::plan(Money::of(45_000), Money::of(30_000), [$this->request(30_000, 30_000), $wrong]);

        self::assertEquals([$wrong->id], $plan->cancel);
        self::assertSame(15_000, $plan->issueAmount?->minor);
        self::assertSame(PaymentRequestPurpose::Supplementary, $plan->issuePurpose);
    }

    public function test_cancelled_and_failed_requests_are_ignored(): void
    {
        $dead = new RequestFacts(PaymentRequestId::generate(), Money::of(30_000), Money::zero(), false);

        $plan = RequestPlanner::plan(Money::of(30_000), Money::zero(), [$dead]);

        self::assertSame([], $plan->cancel);
        self::assertSame(30_000, $plan->issueAmount?->minor);
    }

    public function test_money_revoked_so_nothing_is_held_again_reissues_for_the_full_amount(): void
    {
        $revoked = $this->unpaid(30_000);

        self::assertTrue(RequestPlanner::plan(Money::of(30_000), Money::zero(), [$revoked])->isEmpty());
    }

    private function unpaid(int $amount): RequestFacts
    {
        return $this->request($amount, 0);
    }

    private function request(int $amount, int $paid): RequestFacts
    {
        return new RequestFacts(PaymentRequestId::generate(), Money::of($amount), Money::of($paid), true);
    }
}
