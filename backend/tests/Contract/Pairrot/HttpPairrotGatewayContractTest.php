<?php

declare(strict_types=1);

namespace Eventor\Tests\Contract\Pairrot;

use Eventor\Payments\Application\Port\Exception\PaymentGatewayRejected;
use Eventor\Payments\Application\Port\Exception\PaymentRequestConflict;
use Eventor\Payments\Application\Port\Exception\PaymentRequestHasAllocations;
use Eventor\Payments\Application\Port\PaymentRequestSpec;
use Eventor\Payments\Contract\Exception\BankAccountUnavailable;
use Eventor\Payments\Contract\ReceptionState;
use Eventor\Payments\Infrastructure\Pairrot\HttpPairrotGateway;
use Eventor\Shared\Domain\Money;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpClient\HttpClient;

/**
 * ADR 004 "Verification": `HttpPairrotGateway` against the REAL Pairrot of the compose stack (the `pairrot-sandbox`
 * does not exist yet): provisioning of a subtenant and a bank connection, creation / replay / conflict by
 * `external_id`, the response mapping, cancellation, the event log with its cursor and the refusals.
 * Every test creates its own subtenant and account, so the suite can run repeatedly against the same Pairrot.
 * Skipped when the keys are missing (run `bin/pairrot-bootstrap`). Run: `vendor/bin/phpunit --testsuite contract`.
 */
#[Group('contract')]
final class HttpPairrotGatewayContractTest extends TestCase
{
    private HttpPairrotGateway $gateway;

    protected function setUp(): void
    {
        $base = self::env('PAIRROT_BASE_URL');
        $key = self::env('PAIRROT_MANAGEMENT_KEY');
        if ('' === $base || '' === $key) {
            self::markTestSkipped('Pairrot keys are not configured. Run bin/pairrot-bootstrap.');
        }

        $this->gateway = new HttpPairrotGateway(HttpClient::create([
            'base_uri' => rtrim($base, '/'),
            'auth_bearer' => $key,
            'headers' => ['Accept' => 'application/json'],
            'timeout' => 10,
        ]));
    }

    public function test_a_subtenant_is_created_once_and_found_again(): void
    {
        $external = 'test_contract_'.bin2hex(random_bytes(6));

        $created = $this->gateway->createSubtenant($external, 'Contract club', 'club@example.test');
        $again = $this->gateway->createSubtenant($external, 'Contract club', 'club@example.test');

        self::assertSame($created->id, $again->id, 'the second call finds the existing subtenant after the 409');
        self::assertSame($external, $created->externalId);
        self::assertSame($created->id, $this->gateway->findSubtenant($external)?->id);
        self::assertNull($this->gateway->findSubtenant('test_contract_missing_'.bin2hex(random_bytes(6))));
    }

    public function test_a_bank_connection_with_an_inbound_address_and_its_reception_state(): void
    {
        $subtenant = $this->subtenant();
        $account = self::czechAccount();

        $connection = $this->gateway->connectBankAccount($subtenant, $account, 'Main account');
        $repeated = $this->gateway->connectBankAccount($subtenant, $account, 'Main account');
        $address = $this->gateway->createInboundAddress($subtenant, $connection->id);

        self::assertSame($connection->id, $repeated->id, 'connecting the same account again returns the connection');
        self::assertMatchesRegularExpression('/^CZ\d{22}$/', $connection->iban);
        self::assertStringContainsString('@', $address);
        self::assertSame($address, $this->gateway->createInboundAddress($subtenant, $connection->id), 'the address is assigned once');
        self::assertSame(ReceptionState::Waiting, $this->gateway->receptionState($subtenant, $connection->id));
    }

    public function test_an_account_of_another_subtenant_is_unavailable(): void
    {
        $account = self::czechAccount();
        $this->gateway->connectBankAccount($this->subtenant(), $account, 'Mine');

        $this->expectException(BankAccountUnavailable::class);
        $this->gateway->connectBankAccount($this->subtenant(), $account, 'Squatter');
    }

    public function test_an_invalid_account_number_is_refused_for_good(): void
    {
        try {
            $this->gateway->connectBankAccount($this->subtenant(), '19-2000145398/0800', 'Typo');
            self::fail('Rejected expected');
        } catch (PaymentGatewayRejected $e) {
            self::assertSame(400, $e->status);
            self::assertSame('invalid_account_number', $e->errorCode());
        }
    }

    public function test_a_request_is_created_replayed_and_a_different_body_conflicts(): void
    {
        [$subtenant, $connection] = $this->connected();
        $spec = $this->spec($subtenant, $connection, 'order_contract_'.bin2hex(random_bytes(6)).'_1', 45_000);

        $issued = $this->gateway->createRequest($spec);
        $replayed = $this->gateway->createRequest($spec);

        self::assertFalse($issued->replayed);
        self::assertTrue($replayed->replayed);
        self::assertSame($issued->pairrotId, $replayed->pairrotId);
        self::assertSame($issued->variableSymbol, $replayed->variableSymbol, 'a replay never moves the variable symbol');

        $this->expectException(PaymentRequestConflict::class);
        $this->gateway->createRequest($this->spec($subtenant, $connection, $spec->externalId, 46_000));
    }

    public function test_the_response_is_mapped_to_the_instructions(): void
    {
        [$subtenant, $connection, $iban] = $this->connected();
        $spec = $this->spec($subtenant, $connection, 'order_contract_'.bin2hex(random_bytes(6)).'_1', 45_000);

        $issued = $this->gateway->createRequest($spec);

        self::assertMatchesRegularExpression('/^\d{10}$/', $issued->variableSymbol, 'Pairrot assigns YYYYNNNNNN');
        self::assertSame($iban, $issued->iban);
        self::assertSame(45_000, $issued->amount->minor);
        self::assertSame($spec->dueAt->getTimestamp(), $issued->dueAt->getTimestamp());
        self::assertStringStartsWith('SPD*1.0*ACC:'.$iban.'*AM:450.00*CC:CZK*X-VS:'.$issued->variableSymbol, $issued->spaydPayload);
        self::assertStringContainsString('MSG:EV-2026-000123', $issued->spaydPayload);
        $snapshot = $this->gateway->getRequest($subtenant, $issued->pairrotId);
        self::assertSame('open', $snapshot->status);
        self::assertSame(0, $snapshot->paidAmountMinor);
        self::assertSame(0, $snapshot->paymentChangeSequence);
    }

    public function test_cancelling_works_once_and_is_a_no_op_the_second_time(): void
    {
        [$subtenant, $connection] = $this->connected();
        $issued = $this->gateway->createRequest($this->spec($subtenant, $connection, 'order_contract_'.bin2hex(random_bytes(6)).'_1', 10_000));

        $this->gateway->cancelRequest($subtenant, $issued->pairrotId, 'amount changed');
        $this->gateway->cancelRequest($subtenant, $issued->pairrotId, 'amount changed');

        self::assertSame('cancelled', $this->gateway->getRequest($subtenant, $issued->pairrotId)->status);
        self::assertSame(1, $this->gateway->getRequest($subtenant, $issued->pairrotId)->paymentChangeSequence);
    }

    public function test_the_event_log_returns_webhook_envelopes_with_a_cursor(): void
    {
        [$subtenant, $connection] = $this->connected();
        $ids = [];
        for ($i = 0; $i < 2; ++$i) {
            $issued = $this->gateway->createRequest($this->spec($subtenant, $connection, 'order_contract_'.bin2hex(random_bytes(6)).'_1', 10_000 + $i));
            $this->gateway->cancelRequest($subtenant, $issued->pairrotId, 'test');
            $ids[] = $issued->pairrotId;
        }

        $seen = [];
        $cursor = null;
        $pages = 0;
        do {
            $page = $this->gateway->listWebhookEvents(new \DateTimeImmutable('-10 minutes'), $cursor, 1);
            foreach ($page->events as $event) {
                self::assertIsString($event['id']);
                self::assertSame('payment_request.updated', $event['type'] ?? null);
                $data = $event['data'];
                self::assertIsArray($data);
                $seen[\is_string($data['payment_request_id'] ?? null) ? $data['payment_request_id'] : ''] = $data['cause'] ?? null;
            }
            $cursor = $page->nextCursor;
        } while (null !== $cursor && ++$pages < 200);

        foreach ($ids as $id) {
            self::assertSame('request_cancelled', $seen[$id] ?? null, 'the cancellation of each request is in the log');
        }
        self::assertGreaterThan(1, $pages, 'a page of one event needs the cursor');
    }

    public function test_the_unknown_request_is_a_rejection(): void
    {
        $this->expectException(PaymentGatewayRejected::class);
        $this->gateway->getRequest($this->subtenant(), '0199b7c2-0000-7000-8000-0000000000ee');
    }

    public function test_cancelling_a_request_with_allocations_is_not_possible_to_provoke_without_a_bank_message(): void
    {
        // Pairrot has no API to book an incoming payment (that is what pairrot-sandbox will add): the mapping of
        // 409 payment_request_has_allocations is covered by tests/Unit/Payments/HttpPairrotGatewayTest.
        self::assertTrue(class_exists(PaymentRequestHasAllocations::class));
    }

    private function subtenant(): string
    {
        return $this->gateway->createSubtenant('test_contract_'.bin2hex(random_bytes(6)), 'Contract club', 'club@example.test')->id;
    }

    /**
     * @return array{0: string, 1: string, 2: string} subtenant id, bank connection id, IBAN
     */
    private function connected(): array
    {
        $subtenant = $this->subtenant();
        $connection = $this->gateway->connectBankAccount($subtenant, self::czechAccount(), 'Main');

        return [$subtenant, $connection->id, $connection->iban];
    }

    private function spec(string $subtenant, string $connection, string $externalId, int $amount): PaymentRequestSpec
    {
        return new PaymentRequestSpec($subtenant, $connection, $externalId, Money::of($amount), 'EV-2026-000123', 'Order for the contract test', new \DateTimeImmutable('+7 days'));
    }

    /**
     * A random but valid Czech account number of Česká spořitelna (bank 0800): the check digits are the CNB mod-11 rule,
     * weights 1 2 4 8 5 10 9 7 3 6 from the last digit.
     */
    public static function czechAccount(): string
    {
        $weights = [6, 3, 7, 9, 10, 5, 8, 4, 2, 1];
        while (true) {
            $digits = array_map(static fn(): int => random_int(0, 9), range(1, 10));
            $digits[0] = random_int(1, 9);
            $sum = 0;
            foreach ($digits as $i => $digit) {
                $sum += $digit * $weights[$i];
            }
            if (0 === $sum % 11) {
                return implode('', $digits).'/0800';
            }
        }
    }

    private static function env(string $name): string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);
        if (\is_string($value) && '' !== $value) {
            return $value;
        }

        $file = \dirname(__DIR__, 3).'/.env.local';
        if (is_file($file)) {
            $local = (new Dotenv())->parse((string) file_get_contents($file));
            $value = $local[$name] ?? '';

            return \is_string($value) ? $value : '';
        }

        return '';
    }
}
