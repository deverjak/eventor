<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Payments;

use Eventor\Identity\Domain\Membership\Role;
use Eventor\Payments\Application\Webhook\PairrotWebhookSignature;
use Eventor\Payments\Infrastructure\Pairrot\Webhook\LocalWebhookSecret;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\UserAccountId;
use Eventor\Shared\Domain\Payment\PaymentMethod;
use Eventor\Tests\Support\Foundry\MembershipFactory;
use Eventor\Tests\Support\HttpTestCase;
use Eventor\Tests\Support\Payments\PaymentsFixtures;
use Zenstruck\Foundry\Test\Factories;

/**
 * The HTTP surface of Payments: the signed webhook (API-02) and the admin endpoints (status codes, problem details,
 * 401 / 403 / 404 for a foreign tenant). Money flows are covered by tests/Scenario/Payments.
 */
final class PaymentsHttpTest extends HttpTestCase
{
    use Factories;
    use PaymentsFixtures;

    private OrganizerId $org;
    private string $slug = 'pay-http';

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = OrganizerId::fromString($this->createOwner('owner@example.test', $this->slug)['organizerId']);
    }

    // ------------------------------------------------------------------ webhook

    public function test_a_signed_webhook_is_stored_and_a_duplicate_is_acknowledged_without_a_second_effect(): void
    {
        $signed = $this->signed(['id' => '0199b7c2-0000-7000-8000-0000000000c1', 'type' => 'webhook.test', 'data' => ['synthetic' => true]]);

        $first = $this->webhook($signed);
        self::assertResponseStatusCodeSame(200);
        self::assertSame(['status' => 'accepted'], $first);

        $second = $this->webhook($signed);
        self::assertResponseStatusCodeSame(200);
        self::assertSame(['status' => 'duplicate'], $second);
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM payments.inbox_message WHERE message_id = '0199b7c2-0000-7000-8000-0000000000c1'"));
    }

    public function test_a_wrong_signature_is_401_and_stores_nothing(): void
    {
        $signed = $this->signed(['id' => '0199b7c2-0000-7000-8000-0000000000c2', 'type' => 'webhook.test']);
        $signed['headers']['Pairrot-Webhook-Signature'] = 'v1,local,'.base64_encode(str_repeat('x', 32));

        $body = $this->webhook($signed);

        self::assertResponseStatusCodeSame(401);
        self::assertSame('invalid_signature', $body['code'] ?? null);
        self::assertSame(0, $this->dbInt('SELECT count(*) FROM payments.inbox_message'));
    }

    public function test_a_body_changed_after_signing_is_401(): void
    {
        $signed = $this->signed(['id' => '0199b7c2-0000-7000-8000-0000000000c3', 'type' => 'webhook.test']);
        $signed['body'] .= ' ';

        $this->webhook($signed);

        self::assertResponseStatusCodeSame(401);
    }

    public function test_a_timestamp_outside_five_minutes_is_401(): void
    {
        foreach ([-301, 301] as $offset) {
            $signed = $this->signed(['id' => '0199b7c2-0000-7000-8000-0000000000c4', 'type' => 'webhook.test'], time() + $offset);

            $this->webhook($signed);

            self::assertResponseStatusCodeSame(401, "offset $offset");
        }
    }

    public function test_missing_signature_headers_are_401(): void
    {
        $this->client->request('POST', '/api/webhooks/pairrot', server: ['CONTENT_TYPE' => 'application/json'], content: '{"id":"x","type":"webhook.test"}');

        self::assertResponseStatusCodeSame(401);
    }

    public function test_a_second_signature_of_a_key_rotation_is_enough(): void
    {
        $event = ['id' => '0199b7c2-0000-7000-8000-0000000000c5', 'type' => 'webhook.test'];
        $body = json_encode($event, \JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $header = PairrotWebhookSignature::header('delivery-1', $timestamp, $body, 'retiring', str_repeat("\x09", 32)).' '
            .PairrotWebhookSignature::header('delivery-1', $timestamp, $body, LocalWebhookSecret::KEY_ID, PairrotWebhookSignature::decodeSecret(LocalWebhookSecret::SECRET));

        $this->webhook(['body' => $body, 'headers' => ['Pairrot-Webhook-Id' => 'delivery-1', 'Pairrot-Webhook-Timestamp' => $timestamp, 'Pairrot-Webhook-Signature' => $header]]);

        self::assertResponseStatusCodeSame(200);
    }

    public function test_a_signed_body_that_is_not_an_event_is_400(): void
    {
        $this->webhook($this->signed(['no' => 'id']));

        self::assertResponseStatusCodeSame(400);
    }

    public function test_a_payment_webhook_for_an_unknown_request_is_stored_and_acknowledged(): void
    {
        $this->webhook($this->signed([
            'id' => '0199b7c2-0000-7000-8000-0000000000c6', 'type' => 'payment_request.updated', 'tenant_id' => 't',
            'data' => ['payment_request_id' => '0199b7c2-0000-7000-8000-0000000000dd', 'sequence' => 1, 'cause' => 'allocation_created', 'current' => ['status' => 'paid', 'paid_amount' => 100]],
        ]));

        self::assertResponseStatusCodeSame(200);
        self::assertSame(0, $this->dbInt('SELECT count(*) FROM payments.ledger_entry'));
    }

    // ------------------------------------------------------------------ admin

    public function test_every_admin_endpoint_needs_a_session(): void
    {
        $order = OrderId::generate()->toString();
        foreach ([
            ['GET', "/orders/$order/finance"], ['GET', "/orders/$order/ledger"], ['POST', "/orders/$order/cash"],
            ['GET', '/refund-tasks'], ['GET', '/unmatched-transactions'], ['GET', '/payments/connection'],
        ] as [$method, $path]) {
            $this->request($method, "/api/admin/org/{$this->slug}$path", 'GET' === $method ? null : []);

            $this->assertProblem(401, 'unauthorized');
        }
    }

    public function test_the_finance_of_an_order_and_its_ledger(): void
    {
        $order = $this->placeOrderFinance($this->org, 30_000, PaymentMethod::Cash);
        $this->login('owner@example.test');
        $this->request('POST', "/api/admin/org/{$this->slug}/orders/{$order->toString()}/cash", ['amountMinor' => 10_000, 'note' => 'at the door'], ['Idempotency-Key' => 'http-cash-0000000001']);
        self::assertResponseIsSuccessful();

        $finance = $this->request('GET', "/api/admin/org/{$this->slug}/orders/{$order->toString()}/finance");
        self::assertResponseIsSuccessful();
        self::assertIsArray($finance);
        self::assertSame('partially_paid', $finance['state']);
        self::assertSame(['minor' => 10_000, 'currency' => 'CZK'], $finance['receivedCash']);
        self::assertSame(['minor' => 20_000, 'currency' => 'CZK'], $finance['outstanding']);
        self::assertSame([], $finance['paymentRequests']);
        self::assertNull($finance['openRefundTask']);

        $ledger = $this->request('GET', "/api/admin/org/{$this->slug}/orders/{$order->toString()}/ledger");
        self::assertIsArray($ledger);
        self::assertIsArray($ledger['items']);
        self::assertCount(1, $ledger['items']);
        self::assertSame('cash', self::item($ledger['items'], 0)['kind']);
        self::assertSame('at the door', self::item($ledger['items'], 0)['reason']);
    }

    public function test_cash_is_idempotent_and_needs_a_key_and_a_positive_amount(): void
    {
        $order = $this->placeOrderFinance($this->org, 30_000, PaymentMethod::Cash);
        $this->login('owner@example.test');
        $url = "/api/admin/org/{$this->slug}/orders/{$order->toString()}/cash";

        $first = $this->request('POST', $url, ['amountMinor' => 30_000], ['Idempotency-Key' => 'http-cash-0000000002']);
        self::assertResponseIsSuccessful();
        $again = $this->request('POST', $url, ['amountMinor' => 30_000], ['Idempotency-Key' => 'http-cash-0000000002']);
        self::assertResponseIsSuccessful();
        self::assertIsArray($first);
        self::assertIsArray($again);
        self::assertSame($first['ledgerEntryId'], $again['ledgerEntryId']);
        self::assertSame(1, $this->dbInt('SELECT count(*) FROM payments.ledger_entry WHERE order_id = ?', [$order->toString()]));

        $this->request('POST', $url, ['amountMinor' => 30_000]);
        $this->assertProblem(400, 'idempotency_key_required');

        $this->request('POST', $url, ['amountMinor' => 0], ['Idempotency-Key' => 'http-cash-0000000003']);
        $this->assertProblem(422, 'validation_failed');

        $this->request('POST', $url, ['amountMinor' => 5], ['Idempotency-Key' => 'http-cash-0000000002']);
        $this->assertProblem(409, 'idempotency_key_reused');
    }

    public function test_an_unknown_order_is_404(): void
    {
        $this->login('owner@example.test');
        $unknown = OrderId::generate()->toString();

        $this->request('GET', "/api/admin/org/{$this->slug}/orders/$unknown/finance");
        $this->assertProblem(404, 'order_finance_not_found');

        $this->request('POST', "/api/admin/org/{$this->slug}/orders/$unknown/cash", ['amountMinor' => 100], ['Idempotency-Key' => 'http-cash-0000000004']);
        $this->assertProblem(404, 'order_finance_not_found');

        $this->request('GET', "/api/admin/org/{$this->slug}/orders/not-a-uuid/finance");
        $this->assertProblem(404, 'not_found');
    }

    public function test_an_order_of_another_organizer_is_404_not_403(): void
    {
        $other = OrganizerId::fromString($this->createOwner('other@example.test', 'other-pay')['organizerId']);
        $foreign = $this->placeOrderFinance($other, 30_000, PaymentMethod::Cash);
        $this->login('owner@example.test');

        $this->request('GET', "/api/admin/org/{$this->slug}/orders/{$foreign->toString()}/finance");

        $this->assertProblem(404, 'order_finance_not_found');
    }

    public function test_a_check_in_member_may_not_see_or_book_money(): void
    {
        $order = $this->placeOrderFinance($this->org, 30_000, PaymentMethod::Cash);
        $staff = $this->createOwner('staff@example.test', 'staff-own-club');
        MembershipFactory::createOne([
            'userAccountId' => UserAccountId::fromString($staff['userAccountId']),
            'organizerId' => $this->org,
            'role' => Role::CheckIn,
        ]);
        $this->login('staff@example.test');

        $this->request('GET', "/api/admin/org/{$this->slug}/orders/{$order->toString()}/finance");
        $this->assertProblem(403, 'forbidden');

        $this->request('GET', "/api/admin/org/{$this->slug}/refund-tasks");
        $this->assertProblem(403, 'forbidden');
    }

    public function test_s11_a_cash_entry_is_corrected_over_http(): void
    {
        $order = $this->placeOrderFinance($this->org, 30_000, PaymentMethod::Cash);
        $this->login('owner@example.test');
        $cash = $this->request('POST', "/api/admin/org/{$this->slug}/orders/{$order->toString()}/cash", ['amountMinor' => 50_000], ['Idempotency-Key' => 'http-cash-0000000005']);
        self::assertIsArray($cash);

        $url = "/api/admin/org/{$this->slug}/orders/{$order->toString()}/ledger/".self::s($cash['ledgerEntryId']).'/correction';
        $this->request('POST', $url, ['reason' => ''], ['Idempotency-Key' => 'http-fix-00000000000001']);
        $this->assertProblem(422, 'validation_failed');

        $fixed = $this->request('POST', $url, ['reason' => 'typed 500 instead of 300', 'replacementAmountMinor' => 30_000], ['Idempotency-Key' => 'http-fix-00000000000002']);
        self::assertResponseIsSuccessful();
        self::assertIsArray($fixed);
        self::assertSame('paid', $fixed['state']);

        $this->request('POST', "/api/admin/org/{$this->slug}/orders/{$order->toString()}/ledger/".OrderId::generate()->toString().'/correction', ['reason' => 'nothing there'], ['Idempotency-Key' => 'http-fix-00000000000003']);
        $this->assertProblem(404, 'ledger_entry_not_found');
    }

    public function test_a_manual_payment_needs_a_reason_and_a_non_zero_amount(): void
    {
        $order = $this->placeOrderFinance($this->org, 30_000);
        $this->login('owner@example.test');
        $url = "/api/admin/org/{$this->slug}/orders/{$order->toString()}/manual-payments";

        $this->request('POST', $url, ['amountMinor' => 0, 'reason' => 'voucher'], ['Idempotency-Key' => 'http-man-00000000000001']);
        $this->assertProblem(422, 'validation_failed');

        $body = $this->request('POST', $url, ['amountMinor' => 30_000, 'reason' => 'voucher from sponsor'], ['Idempotency-Key' => 'http-man-00000000000002']);
        self::assertResponseIsSuccessful();
        self::assertIsArray($body);
        self::assertSame('paid', $body['state']);
    }

    public function test_the_refund_task_lifecycle_over_http(): void
    {
        $order = $this->placeOrderFinance($this->org, 30_000, PaymentMethod::Cash);
        $this->login('owner@example.test');
        $base = "/api/admin/org/{$this->slug}/refund-tasks";
        $this->request('POST', "/api/admin/org/{$this->slug}/orders/{$order->toString()}/cash", ['amountMinor' => 35_000], ['Idempotency-Key' => 'http-cash-0000000006']);

        $this->request('POST', $base, ['orderId' => $order->toString(), 'amountMinor' => 5_000, 'reason' => 'nonsense']);
        $this->assertProblem(422, 'validation_failed');

        $created = $this->request('POST', $base, ['orderId' => $order->toString(), 'amountMinor' => 5_000, 'reason' => 'overpayment', 'note' => 'returns by transfer']);
        self::assertResponseStatusCodeSame(201);
        self::assertIsArray($created);
        $task = self::s($created['refundTaskId']);

        $this->request('POST', $base, ['orderId' => $order->toString(), 'amountMinor' => 1_000, 'reason' => 'overpayment']);
        $this->assertProblem(409, 'refund_task_not_open');

        $list = $this->request('GET', $base.'?status=open');
        self::assertIsArray($list);
        self::assertIsArray($list['items']);
        self::assertCount(1, $list['items']);
        self::assertSame('OPEN', self::item($list['items'], 0)['status']);
        $this->request('GET', $base.'?status=bogus');
        $this->assertProblem(422, 'validation_failed');

        $settled = $this->request('POST', "$base/$task/settle", ['method' => 'bank_transfer', 'reference' => 'tx-1']);
        self::assertResponseIsSuccessful();
        self::assertIsArray($settled);
        self::assertSame('paid', $settled['state']);

        $this->request('POST', "$base/$task/settle", ['method' => 'bank_transfer']);
        $this->assertProblem(409, 'refund_task_not_open');
        $this->request('POST', "$base/".OrderId::generate()->toString().'/cancel', ['reason' => 'nothing']);
        $this->assertProblem(404, 'refund_task_not_found');
    }

    public function test_a_refund_cannot_exceed_the_money_the_order_received(): void
    {
        $unpaid = $this->placeOrderFinance($this->org, 30_000, PaymentMethod::Cash);
        $paid = $this->placeOrderFinance($this->org, 30_000, PaymentMethod::Cash);
        $this->login('owner@example.test');
        $base = "/api/admin/org/{$this->slug}/refund-tasks";
        $this->request('POST', "/api/admin/org/{$this->slug}/orders/{$paid->toString()}/cash", ['amountMinor' => 30_000], ['Idempotency-Key' => 'http-cash-0000000008']);

        $this->request('POST', $base, ['orderId' => $unpaid->toString(), 'amountMinor' => 10_000, 'reason' => 'overpayment']);
        $this->assertProblem(422, 'refund_exceeds_balance');
        $this->request('POST', $base, ['orderId' => $paid->toString(), 'amountMinor' => 30_001, 'reason' => 'overpayment']);
        $this->assertProblem(422, 'refund_exceeds_balance');

        $this->request('POST', $base, ['orderId' => $paid->toString(), 'amountMinor' => 30_000, 'reason' => 'overpayment']);
        self::assertResponseStatusCodeSame(201);
        self::assertSame(0, $this->dbInt('SELECT count(*) FROM payments.refund_task WHERE order_id = ?', [$unpaid->toString()]));
    }

    public function test_a_refund_task_can_be_cancelled(): void
    {
        $order = $this->placeOrderFinance($this->org, 30_000, PaymentMethod::Cash);
        $this->login('owner@example.test');
        $this->request('POST', "/api/admin/org/{$this->slug}/orders/{$order->toString()}/cash", ['amountMinor' => 35_000], ['Idempotency-Key' => 'http-cash-0000000007']);
        $created = $this->request('POST', "/api/admin/org/{$this->slug}/refund-tasks", ['orderId' => $order->toString(), 'amountMinor' => 5_000, 'reason' => 'overpayment']);
        self::assertIsArray($created);

        $this->request('POST', "/api/admin/org/{$this->slug}/refund-tasks/".self::s($created['refundTaskId']).'/cancel', ['reason' => 'participant donated it']);

        self::assertResponseIsSuccessful();
        self::assertSame('overpaid', $this->financeState($order));
    }

    public function test_the_reconciliation_queue_and_the_connection_status_start_empty(): void
    {
        $this->login('owner@example.test');

        $queue = $this->request('GET', "/api/admin/org/{$this->slug}/unmatched-transactions");
        self::assertResponseIsSuccessful();
        self::assertSame(['items' => []], $queue);

        $this->request('GET', "/api/admin/org/{$this->slug}/unmatched-transactions/".OrderId::generate()->toString().'/candidates');
        $this->assertProblem(404, 'unmatched_transaction_not_found');

        $this->request('POST', "/api/admin/org/{$this->slug}/unmatched-transactions/".OrderId::generate()->toString().'/allocate', ['orderId' => OrderId::generate()->toString(), 'reason' => 'test']);
        $this->assertProblem(404, 'unmatched_transaction_not_found');

        $status = $this->request('GET', "/api/admin/org/{$this->slug}/payments/connection");
        self::assertResponseIsSuccessful();
        self::assertIsArray($status);
        self::assertFalse($status['connected']);
    }

    public function test_the_connection_status_of_a_connected_organizer(): void
    {
        $this->connectOrganizer($this->org);
        $this->login('owner@example.test');

        $status = $this->request('GET', "/api/admin/org/{$this->slug}/payments/connection");

        self::assertResponseIsSuccessful();
        self::assertIsArray($status);
        self::assertTrue($status['connected']);
        self::assertTrue($status['pairrotReachable']);
        self::assertSame('waiting', $status['receptionState']);
        self::assertStringContainsString('****', self::s($status['maskedIban']));
    }

    public function test_a_payment_request_that_has_not_failed_cannot_be_retried(): void
    {
        $this->login('owner@example.test');

        $this->request('POST', "/api/admin/org/{$this->slug}/payment-requests/".OrderId::generate()->toString().'/retry', []);

        $this->assertProblem(404, 'payment_request_not_found');
    }

    private static function s(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }

    /**
     * @return array<string, mixed>
     */
    private static function item(mixed $items, int $index): array
    {
        $item = \is_array($items) ? ($items[$index] ?? []) : [];
        /** @var array<string, mixed> $result */
        $result = \is_array($item) ? $item : [];

        return $result;
    }

    /**
     * @param array<string, mixed> $event
     *
     * @return array{body: string, headers: array<string, string>}
     */
    private function signed(array $event, ?int $at = null): array
    {
        return $this->pairrot()->signWebhook($event, null === $at ? null : new \DateTimeImmutable('@'.$at));
    }

    /**
     * @param array{body: string, headers: array<string, string>} $signed
     *
     * @return array<array-key, mixed>
     */
    private function webhook(array $signed): array
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        foreach ($signed['headers'] as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }
        $this->client->request('POST', '/api/webhooks/pairrot', server: $server, content: $signed['body']);

        /** @var array<array-key, mixed> $decoded */
        $decoded = json_decode((string) $this->client->getResponse()->getContent(), true) ?? [];

        return $decoded;
    }
}
