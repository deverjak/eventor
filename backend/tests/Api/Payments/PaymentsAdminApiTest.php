<?php

declare(strict_types=1);

namespace Eventor\Tests\Api\Payments;

use Eventor\Tests\Api\Support\ApiFixtures;
use Eventor\Tests\Api\Support\ApiTestCase;

/**
 * The admin payments endpoints that need no order (full order-to-payment flows come with Registration in W3): empty
 * queues, validation, the guard order 401 -> 404 -> 403 and the tenant boundary.
 */
final class PaymentsAdminApiTest extends ApiTestCase
{
    private const string NOBODY = '0199a000-0000-7000-8000-000000000000';

    private function base(): string
    {
        return '/api/admin/org/'.ApiFixtures::ALPHA['slug'];
    }

    public function test_the_queues_are_readable_and_empty_for_a_new_organizer(): void
    {
        $owner = $this->loginAs(ApiFixtures::ALPHA);

        $refunds = $owner->get($this->base().'/refund-tasks');
        self::assertSame(200, $refunds->status, $refunds->body);
        self::assertSame(['items' => []], $refunds->json());

        $unmatched = $owner->get($this->base().'/unmatched-transactions');
        self::assertSame(200, $unmatched->status);
        self::assertSame(['items' => []], $unmatched->json());

        $connection = $owner->get($this->base().'/payments/connection');
        self::assertSame(200, $connection->status);
        self::assertFalse($connection->json()['connected']);
    }

    public function test_every_endpoint_answers_401_without_a_session(): void
    {
        $anonymous = $this->anonymous();
        foreach (['/refund-tasks', '/unmatched-transactions', '/payments/connection', '/orders/'.self::NOBODY.'/finance', '/orders/'.self::NOBODY.'/ledger'] as $path) {
            $this->assertProblem($anonymous->get($this->base().$path), 401, 'unauthorized');
        }
        $this->assertProblem($anonymous->post($this->base().'/orders/'.self::NOBODY.'/cash', ['amountMinor' => 100]), 401, 'unauthorized');
    }

    public function test_a_foreign_organizer_is_404(): void
    {
        $alpha = $this->loginAs(ApiFixtures::ALPHA);

        foreach (['/refund-tasks', '/unmatched-transactions', '/payments/connection'] as $path) {
            $this->assertProblem($alpha->get('/api/admin/org/'.ApiFixtures::BETA['slug'].$path), 404, 'not_found');
        }
    }

    public function test_an_unknown_order_is_a_404_problem(): void
    {
        $owner = $this->loginAs(ApiFixtures::ALPHA);

        $this->assertProblem($owner->get($this->base().'/orders/'.self::NOBODY.'/finance'), 404, 'order_finance_not_found');
        $this->assertProblem($owner->get($this->base().'/orders/'.self::NOBODY.'/ledger'), 404, 'order_finance_not_found');
        $this->assertProblem($owner->post($this->base().'/orders/'.self::NOBODY.'/cash', ['amountMinor' => 100], ['Idempotency-Key' => bin2hex(random_bytes(12))]), 404, 'order_finance_not_found');
    }

    public function test_cash_validation(): void
    {
        $owner = $this->loginAs(ApiFixtures::ALPHA);
        $url = $this->base().'/orders/'.self::NOBODY.'/cash';

        $this->assertProblem($owner->post($url, ['amountMinor' => 100]), 400, 'idempotency_key_required');
        $this->assertProblem($owner->post($url, ['amountMinor' => -5], ['Idempotency-Key' => bin2hex(random_bytes(12))]), 422, 'validation_failed');
    }

    public function test_refund_task_validation_and_unknown_task(): void
    {
        $owner = $this->loginAs(ApiFixtures::ALPHA);

        $this->assertProblem($owner->post($this->base().'/refund-tasks', ['orderId' => 'x', 'amountMinor' => 0, 'reason' => 'nope']), 422, 'validation_failed');
        $this->assertProblem($owner->post($this->base().'/refund-tasks/'.self::NOBODY.'/settle', ['method' => 'bank_transfer']), 404, 'refund_task_not_found');
        $this->assertProblem($owner->post($this->base().'/refund-tasks/'.self::NOBODY.'/cancel', ['reason' => 'not needed']), 404, 'refund_task_not_found');
        $this->assertProblem($owner->get($this->base().'/refund-tasks?status=bogus'), 422, 'validation_failed');
    }

    public function test_reconciliation_endpoints_for_an_unknown_transaction(): void
    {
        $owner = $this->loginAs(ApiFixtures::ALPHA);

        $this->assertProblem($owner->get($this->base().'/unmatched-transactions/'.self::NOBODY.'/candidates'), 404, 'unmatched_transaction_not_found');
        $this->assertProblem($owner->post($this->base().'/unmatched-transactions/'.self::NOBODY.'/allocate', ['orderId' => self::NOBODY, 'reason' => 'wrong symbol']), 404, 'unmatched_transaction_not_found');
        $this->assertProblem($owner->post($this->base().'/unmatched-transactions/'.self::NOBODY.'/allocate', ['orderId' => 'nope']), 422, 'validation_failed');
    }

    public function test_retrying_an_unknown_payment_request_is_404(): void
    {
        $owner = $this->loginAs(ApiFixtures::ALPHA);

        $this->assertProblem($owner->post($this->base().'/payment-requests/'.self::NOBODY.'/retry'), 404, 'payment_request_not_found');
    }

    public function test_manual_payment_and_correction_validation(): void
    {
        $owner = $this->loginAs(ApiFixtures::ALPHA);
        $key = ['Idempotency-Key' => bin2hex(random_bytes(12))];

        $this->assertProblem($owner->post($this->base().'/orders/'.self::NOBODY.'/manual-payments', ['amountMinor' => 0, 'reason' => 'voucher'], $key), 422, 'validation_failed');
        $this->assertProblem($owner->post($this->base().'/orders/'.self::NOBODY.'/ledger/'.self::NOBODY.'/correction', ['reason' => 'typo'], $key), 404, 'order_finance_not_found');
    }
}
