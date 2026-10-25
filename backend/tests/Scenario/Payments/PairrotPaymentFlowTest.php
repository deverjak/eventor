<?php

declare(strict_types=1);

namespace Eventor\Tests\Scenario\Payments;

use Eventor\Payments\Application\Refund\SettleRefundTask;
use Eventor\Payments\Application\Webhook\ReconcilePairrotEvents;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Tests\Scenario\ScenarioTestCase;
use Eventor\Tests\Support\Payments\DrainsPaymentsQueues;
use Eventor\Tests\Support\Payments\PaymentsFixtures;

/**
 * S03 - S08 and the money paths around them, end to end against the in-memory Pairrot: order placed -> payment request
 * (outbox subscriber) -> signed webhook -> inbox -> ledger -> financial state, with the real Messenger worker draining
 * `payments_events` and `pairrot_inbox`.
 */
final class PairrotPaymentFlowTest extends ScenarioTestCase
{
    use DrainsPaymentsQueues;
    use PaymentsFixtures;

    private OrganizerId $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = OrganizerId::fromString($this->createOwner('fin@example.test', 'pay-club')['organizerId']);
        $this->connectOrganizer($this->org);
        $this->dispatchAs($this->org, new \Eventor\Identity\Application\ChangePolicy\ChangeOrganizerPolicy($this->org, 7, 72, false, \Eventor\Identity\Contract\OverpaymentHandling::Refund, false, false));
    }

    public function test_an_order_gets_a_payment_request_with_the_frozen_instructions(): void
    {
        $order = $this->placeTransferOrder($this->org, 30_000, 'EV-2026-000123');
        $this->drainQueues();

        $row = $this->requestRow($order);
        self::assertSame('CREATED', $row['creation']);
        self::assertSame('issued', $row['status']);
        self::assertSame('initial', $row['purpose']);
        self::assertSame(30_000, $row['amount']);
        $request = $this->pairrot()->requestByExternalId('order_'.$order->toString().'_1');
        self::assertNotNull($request);
        self::assertSame($request['vs'], $this->dbString('SELECT variable_symbol FROM payments.payment_request WHERE order_id = ?', [$order->toString()]));
        self::assertStringContainsString('SPD*1.0*ACC:', $this->dbString('SELECT spayd FROM payments.payment_request WHERE order_id = ?', [$order->toString()]));
        self::assertStringContainsString('EV-2026-000123', $this->dbString('SELECT message FROM payments.payment_request WHERE order_id = ?', [$order->toString()]));
    }

    public function test_cash_and_free_orders_get_no_payment_request(): void
    {
        $order = $this->placeOrderFinance($this->org, 30_000, \Eventor\Shared\Domain\Payment\PaymentMethod::Cash);
        $this->publish($this->org, new \Eventor\Registration\Contract\Event\OrderPlaced(
            $this->org,
            $order,
            'EV-2026-000124',
            \Eventor\Shared\Domain\Id\EventId::generate(),
            \Eventor\Shared\Domain\Payment\PaymentMethod::Cash,
            \Eventor\Shared\Domain\Money::of(30_000),
            [\Eventor\Shared\Domain\Id\ParticipationId::generate()],
            null,
            new \DateTimeImmutable('2026-11-01T12:00:00+00:00'),
        ));
        $this->drainQueues();

        self::assertSame(0, $this->dbInt('SELECT count(*) FROM payments.payment_request WHERE order_id = ?', [$order->toString()]));
        self::assertSame(0, $this->pairrot()->countCalls('create_request'));
    }

    public function test_s03_the_same_payment_delivered_three_times_is_booked_once(): void
    {
        $order = $this->placeTransferOrder($this->org, 30_000);
        $this->drainQueues();
        $event = $this->pairrot()->receivePayment($this->requestRow($order)['pairrotId'], 30_000);

        self::assertSame('accepted', $this->deliverWebhook($event));
        self::assertSame('duplicate', $this->deliverWebhook($event));
        self::assertSame('duplicate', $this->deliverWebhook($event));
        $this->drainQueues();

        self::assertSame(1, $this->dbInt("SELECT count(*) FROM payments.ledger_entry WHERE order_id = ? AND kind = 'pairrot_allocation'", [$order->toString()]));
        self::assertSame(30_000, $this->dbInt('SELECT received_minor FROM payments.order_finance WHERE order_id = ?', [$order->toString()]));
        self::assertSame('paid', $this->financeState($order));
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM payments.inbox_message WHERE message_id = ? AND status = 'processed'", [$event['id']]));
    }

    public function test_s04_a_partial_payment_then_the_rest(): void
    {
        $order = $this->placeTransferOrder($this->org, 30_000);
        $this->drainQueues();
        $request = $this->requestRow($order)['pairrotId'];

        $this->deliverWebhook($this->pairrot()->receivePayment($request, 10_000));
        $this->drainQueues();
        self::assertSame('partially_paid', $this->financeState($order));
        self::assertSame(10_000, $this->requestRow($order)['paid']);

        $this->deliverWebhook($this->pairrot()->receivePayment($request, 20_000));
        $this->drainQueues();
        self::assertSame('paid', $this->financeState($order));
        self::assertSame(2, $this->dbInt("SELECT count(*) FROM payments.ledger_entry WHERE order_id = ? AND kind = 'pairrot_allocation'", [$order->toString()]));
    }

    public function test_an_older_event_arriving_late_changes_nothing(): void
    {
        $order = $this->placeTransferOrder($this->org, 30_000);
        $this->drainQueues();
        $request = $this->requestRow($order)['pairrotId'];

        $first = $this->pairrot()->receivePayment($request, 10_000);
        $second = $this->pairrot()->receivePayment($request, 20_000);
        $this->deliverWebhook($second); // the newer one first
        $this->drainQueues();
        $this->deliverWebhook($first);
        $this->drainQueues();

        self::assertSame('paid', $this->financeState($order));
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM payments.ledger_entry WHERE order_id = ? AND kind = 'pairrot_allocation'", [$order->toString()]), 'the whole 30 000 was booked as one delta; the older event is only an echo');
        self::assertSame(30_000, $this->requestRow($order)['paid']);
    }

    public function test_a_revoked_allocation_reopens_the_order(): void
    {
        $order = $this->placeTransferOrder($this->org, 30_000);
        $this->drainQueues();
        $request = $this->requestRow($order)['pairrotId'];
        $this->deliverWebhook($this->pairrot()->receivePayment($request, 30_000));
        $this->drainQueues();

        $this->deliverWebhook($this->pairrot()->revoke($request, 30_000));
        $this->drainQueues();

        self::assertSame('unpaid', $this->financeState($order));
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM payments.ledger_entry WHERE order_id = ? AND kind = 'pairrot_revocation'", [$order->toString()]));
        self::assertSame(
            $this->dbString("SELECT id FROM payments.ledger_entry WHERE order_id = ? AND kind = 'pairrot_allocation'", [$order->toString()]),
            $this->dbString("SELECT compensates_id FROM payments.ledger_entry WHERE order_id = ? AND kind = 'pairrot_revocation'", [$order->toString()]),
        );
    }

    public function test_a_review_case_marks_the_order_disputed(): void
    {
        $order = $this->placeTransferOrder($this->org, 30_000);
        $this->drainQueues();
        $request = $this->requestRow($order)['pairrotId'];
        $this->deliverWebhook($this->pairrot()->receivePayment($request, 30_000));
        $this->drainQueues();

        $this->deliverWebhook($this->pairrot()->reviewRequired($request, 30_000));
        $this->drainQueues();

        self::assertSame('disputed', $this->financeState($order));
        self::assertSame(0, $this->dbInt('SELECT received_minor FROM payments.order_finance WHERE order_id = ?', [$order->toString()]), 'the allocation was taken away while the case is open');
    }

    public function test_s07_an_overpayment_opens_a_refund_task_and_settling_it_closes_the_books(): void
    {
        $order = $this->placeTransferOrder($this->org, 30_000);
        $this->drainQueues();
        $this->deliverWebhook($this->pairrot()->receivePayment($this->requestRow($order)['pairrotId'], 35_000));
        $this->drainQueues();

        self::assertSame('refund_pending', $this->financeState($order));
        $task = $this->dbRow('SELECT id, amount_minor, reason, status FROM payments.refund_task WHERE order_id = ?', [$order->toString()]);
        self::assertSame(5_000, self::n($task['amount_minor']));
        self::assertSame('overpayment', $task['reason']);
        self::assertSame('OPEN', $task['status']);

        $this->dispatchAs($this->org, new SettleRefundTask(self::t($task['id']), 'bank_transfer', 'ref-1', 'returned'));

        self::assertSame('paid', $this->financeState($order));
        self::assertSame('SETTLED', $this->dbString('SELECT status FROM payments.refund_task WHERE order_id = ?', [$order->toString()]));
        self::assertSame(-5_000, $this->dbInt("SELECT amount_minor FROM payments.ledger_entry WHERE kind = 'refund_settled' AND order_id = ?", [$order->toString()]));
        self::assertSame(5_000, $this->dbInt('SELECT refunded_minor FROM payments.order_finance WHERE order_id = ?', [$order->toString()]));
    }

    public function test_s08_pairrot_down_the_order_is_kept_and_the_request_is_created_after_recovery(): void
    {
        $this->tolerateWorkerFailures = true;
        $this->pairrot()->failNext(5); // more than one Retrying attempt (3 calls), fewer than the outbox gives up after

        $order = $this->placeTransferOrder($this->org, 30_000);
        $first = $this->drainWithRetries();

        self::assertGreaterThan(0, $first->failed, 'the subscriber failed and the outbox rescheduled it');
        self::assertSame('CREATED', $this->requestRow($order)['creation'], 'handled='.$first->handled.' failed='.$first->failed.' '.json_encode($this->dbRows('SELECT creation_state, failed_attempts, last_error FROM payments.payment_request')).' calls='.json_encode(array_column($this->pairrot()->calls(), 'operation')));
        self::assertSame(1, $this->dbInt('SELECT count(*) FROM payments.payment_request WHERE order_id = ?', [$order->toString()]), 'one request, attempt 1, whatever the number of tries');
        self::assertSame('order_'.$order->toString().'_1', $this->dbString('SELECT external_id FROM payments.payment_request WHERE order_id = ?', [$order->toString()]));
        self::assertGreaterThan(0, $this->dbInt('SELECT count(*) FROM payments.order_finance WHERE order_id = ?', [$order->toString()]), 'the order finance is untouched by the outage');
    }

    public function test_s08_when_the_retries_run_out_the_request_fails_and_can_be_retried(): void
    {
        $this->tolerateWorkerFailures = true;
        $this->pairrot()->outage();

        $order = $this->placeTransferOrder($this->org, 30_000);
        $this->drainWithRetries();

        $row = $this->requestRow($order);
        self::assertSame('FAILED', $row['creation']);
        self::assertSame('failed', $row['status']);
        self::assertGreaterThanOrEqual(1, $this->dbInt('SELECT failed_attempts FROM payments.payment_request WHERE order_id = ?', [$order->toString()]));
        self::assertSame('unpaid', $this->financeState($order), 'the order itself is fine');

        $this->pairrot()->outage(false);
        \Symfony\Component\Clock\Clock::get()->sleep(120); // the circuit breaker pauses Pairrot for a minute after five failures
        $this->enterTenant($this->org);
        $this->dispatchAs($this->org, new \Eventor\Payments\Application\PaymentRequest\RetryPaymentRequest($row['id']));
        $issuer = static::getContainer()->get(\Eventor\Payments\Application\PaymentRequest\PaymentRequestIssuer::class);
        \assert($issuer instanceof \Eventor\Payments\Application\PaymentRequest\PaymentRequestIssuer);
        $issuer->issue(\Eventor\Shared\Domain\Id\PaymentRequestId::fromString($row['id']));

        self::assertSame('CREATED', $this->requestRow($order)['creation']);
        self::assertSame(1, $this->dbInt('SELECT count(*) FROM payments.payment_request WHERE order_id = ?', [$order->toString()]), 'the same attempt, the same external id');
    }

    public function test_the_debt_rose_before_payment_cancels_the_old_request_and_issues_a_new_one(): void
    {
        $order = $this->placeTransferOrder($this->org, 30_000);
        $this->drainQueues();

        $this->changeAmountDue($this->org, $order, 30_000, 45_000);
        $this->drainQueues();

        $old = $this->requestRow($order, 1);
        $new = $this->requestRow($order, 2);
        self::assertSame('cancelled', $old['status']);
        self::assertSame('retry', $new['purpose']);
        self::assertSame(45_000, $new['amount']);
        self::assertSame('CREATED', $new['creation']);
        self::assertTrue($this->pairrot()->requestByExternalId('order_'.$order->toString().'_1')['cancelled'] ?? false);
        self::assertSame(45_000, $this->dbInt('SELECT due_minor FROM payments.order_finance WHERE order_id = ?', [$order->toString()]));
    }

    public function test_s18_the_debt_rose_after_payment_issues_a_supplementary_request(): void
    {
        $order = $this->placeTransferOrder($this->org, 30_000);
        $this->drainQueues();
        $this->deliverWebhook($this->pairrot()->receivePayment($this->requestRow($order)['pairrotId'], 30_000));
        $this->drainQueues();

        $this->changeAmountDue($this->org, $order, 30_000, 40_000);
        $this->drainQueues();

        self::assertSame('partially_paid', $this->financeState($order));
        $supplementary = $this->requestRow($order, 2);
        self::assertSame('supplementary', $supplementary['purpose']);
        self::assertSame(10_000, $supplementary['amount']);
        self::assertSame('paid', $this->requestRow($order, 1)['status'], 'the paid request is not cancelled');

        // Redelivery of the same event creates nothing new.
        $this->changeAmountDue($this->org, $order, 30_000, 40_000);
        $this->drainQueues();
        self::assertSame(2, $this->dbInt('SELECT count(*) FROM payments.payment_request WHERE order_id = ?', [$order->toString()]));
    }

    public function test_the_debt_fell_after_payment_opens_a_refund_task(): void
    {
        $order = $this->placeTransferOrder($this->org, 30_000);
        $this->drainQueues();
        $this->deliverWebhook($this->pairrot()->receivePayment($this->requestRow($order)['pairrotId'], 30_000));
        $this->drainQueues();

        $this->changeAmountDue($this->org, $order, 30_000, 0, \Eventor\Registration\Contract\OrderAmountChangeReason::EventCancelled);
        $this->drainQueues();

        self::assertSame('refund_pending', $this->financeState($order));
        self::assertSame('event_cancelled', $this->dbString('SELECT reason FROM payments.refund_task WHERE order_id = ?', [$order->toString()]));
        self::assertSame(30_000, $this->dbInt('SELECT amount_minor FROM payments.refund_task WHERE order_id = ?', [$order->toString()]));
    }

    public function test_an_unpaid_order_that_is_cancelled_cancels_its_request_and_needs_no_refund(): void
    {
        $order = $this->placeTransferOrder($this->org, 30_000);
        $this->drainQueues();

        $this->changeAmountDue($this->org, $order, 30_000, 0, \Eventor\Registration\Contract\OrderAmountChangeReason::EventCancelled);
        $this->drainQueues();

        self::assertSame('not_required', $this->financeState($order));
        self::assertSame('cancelled', $this->requestRow($order)['status']);
        self::assertSame(0, $this->dbInt('SELECT count(*) FROM payments.refund_task WHERE order_id = ?', [$order->toString()]));
    }

    public function test_the_expired_participation_keeps_its_request_so_a_late_payment_still_lands(): void
    {
        // Payments never cancels a request because a reservation expired (S05/S06): the late webhook is booked normally.
        $order = $this->placeTransferOrder($this->org, 30_000);
        $this->drainQueues();
        \Symfony\Component\Clock\Clock::get()->sleep(10 * 86400);

        $this->deliverWebhook($this->pairrot()->receivePayment($this->requestRow($order)['pairrotId'], 30_000));
        $this->drainQueues();

        self::assertSame('paid', $this->financeState($order));
    }

    public function test_the_reconcile_job_recovers_a_lost_webhook_and_is_idempotent(): void
    {
        $order = $this->placeTransferOrder($this->org, 30_000);
        $this->drainQueues();
        $this->pairrot()->receivePayment($this->requestRow($order)['pairrotId'], 30_000); // the webhook never arrives

        $result = $this->dispatchReconcile();
        $this->drainQueues();
        self::assertSame('paid', $this->financeState($order));
        self::assertGreaterThan(0, $result['new']);

        $again = $this->dispatchReconcile();
        $this->drainQueues();
        self::assertSame(0, $again['new'], 'every event is already in the inbox');
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM payments.ledger_entry WHERE order_id = ? AND kind = 'pairrot_allocation'", [$order->toString()]));
    }

    public function test_a_webhook_for_an_unknown_request_is_rejected_and_surfaced(): void
    {
        $unknown = [
            'id' => '0199b7c2-0000-7000-8000-000000000001',
            'type' => 'payment_request.updated',
            'schema_version' => 1,
            'created_at' => '2026-11-01T11:00:00.000000Z',
            'environment' => 'live',
            'tenant_id' => 'x',
            'data' => ['payment_request_id' => '0199b7c2-0000-7000-8000-0000000000aa', 'external_id' => 'order_x_1', 'sequence' => 1, 'cause' => 'allocation_created', 'current' => ['status' => 'paid', 'paid_amount' => 100]],
        ];

        $this->deliverWebhook($unknown);
        $this->drainQueues();

        self::assertSame('rejected', $this->dbString("SELECT status FROM payments.inbox_message WHERE message_id = '0199b7c2-0000-7000-8000-000000000001'"));
        self::assertSame(0, $this->dbInt('SELECT count(*) FROM payments.ledger_entry'));
    }

    public function test_an_unmatched_transaction_enters_the_queue_and_is_allocated_through_pairrot(): void
    {
        $order = $this->placeTransferOrder($this->org, 30_000);
        $this->drainQueues();
        $account = $this->dbString('SELECT subtenant_id FROM payments.organizer_pairrot_account WHERE organizer_id = ?', [$this->org->toString()]);

        $event = $this->pairrot()->receiveUnmatchedTransaction($account, 12_000, '000999', 'request_not_found');
        $this->deliverWebhook($event);
        $this->drainQueues();

        $row = $this->dbRow('SELECT id, amount_minor, matching_result, resolved_at FROM payments.unmatched_transaction');
        self::assertSame(12_000, self::n($row['amount_minor']));
        self::assertSame('request_not_found', $row['matching_result']);
        self::assertNull($row['resolved_at']);

        $allocator = static::getContainer()->get(\Eventor\Payments\Application\Unmatched\UnmatchedAllocator::class);
        \assert($allocator instanceof \Eventor\Payments\Application\Unmatched\UnmatchedAllocator);
        $this->enterTenant($this->org);
        $allocator->allocate($this->org, \Eventor\Shared\Domain\Id\BankTransactionId::fromString(self::t($row['id'])), $order, null, 'Payer wrote the wrong symbol');

        // Pairrot allocated the money to the request; the ledger changes only when its webhook comes back (here: the reconcile job).
        self::assertSame('unpaid', $this->financeState($order), 'the allocation does not write the ledger by itself');
        $this->dispatchReconcile();
        $this->drainQueues();

        self::assertSame('partially_paid', $this->financeState($order));
        self::assertSame(12_000, $this->requestRow($order)['paid']);
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM audit.audit_entry WHERE action = 'unmatched_transaction.allocation_requested' AND reason = 'Payer wrote the wrong symbol'"));
    }

    public function test_an_inbound_address_pause_and_unknown_event_types_are_acknowledged(): void
    {
        $account = $this->dbString('SELECT subtenant_id FROM payments.organizer_pairrot_account WHERE organizer_id = ?', [$this->org->toString()]);

        $paused = [
            'id' => '0199b7c2-0000-7000-8000-0000000000b1', 'type' => 'inbound_address.paused', 'schema_version' => 1,
            'created_at' => '2026-11-01T11:00:00.000000Z', 'environment' => 'live', 'tenant_id' => $account,
            'data' => ['subject_id' => 'x', 'sequence' => 1, 'cause' => 'foreign_mail_limit', 'inbound_address_id' => 'a', 'bank_connection_id' => 'b'],
        ];
        $test = ['id' => '0199b7c2-0000-7000-8000-0000000000b2', 'type' => 'webhook.test', 'schema_version' => 1, 'created_at' => '2026-11-01T11:00:00.000000Z', 'environment' => 'live', 'tenant_id' => $account, 'data' => ['synthetic' => true]];

        self::assertSame('accepted', $this->deliverWebhook($paused));
        self::assertSame('accepted', $this->deliverWebhook($test));
        $this->drainQueues();

        self::assertSame('processed', $this->dbString("SELECT status FROM payments.inbox_message WHERE message_id = '0199b7c2-0000-7000-8000-0000000000b1'"));
        self::assertSame('processed', $this->dbString("SELECT status FROM payments.inbox_message WHERE message_id = '0199b7c2-0000-7000-8000-0000000000b2'"));
    }

    /**
     * @return array{events: int, new: int, snapshots: int}
     */
    private function dispatchReconcile(): array
    {
        /** @var array{events: int, new: int, snapshots: int} $result */
        $result = $this->commandBus()->dispatch(new ReconcilePairrotEvents());

        return $result;
    }
}
