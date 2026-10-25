<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Query;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Eventor\Payments\Contract\Exception\OrderFinanceNotFound;
use Eventor\Payments\Contract\FinanceQuery;
use Eventor\Payments\Contract\OrderFinanceView;
use Eventor\Payments\Contract\PaymentInstructionsView;
use Eventor\Payments\Contract\PaymentRequestPurpose;
use Eventor\Payments\Contract\PaymentRequestStatus;
use Eventor\Shared\Domain\Currency;
use Eventor\Shared\Domain\Finance\FinancialState;
use Eventor\Shared\Domain\Finance\FinancialStateView;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\PaymentRequestId;
use Eventor\Shared\Domain\Money;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * `FinanceQuery` over DBAL: reads the cached fold and the mirrored payment requests. Every query names the organizer,
 * so an order of another organizer is "not found" (ADR 005).
 */
#[AsAlias(FinanceQuery::class)]
final readonly class OrderFinanceQuery implements FinanceQuery
{
    public function __construct(private Connection $connection) {}

    public function forOrder(OrganizerId $organizer, OrderId $order): OrderFinanceView
    {
        /** @var array<string, mixed>|false $row */
        $row = $this->connection->fetchAssociative(
            "SELECT f.*,
                    EXISTS (SELECT 1 FROM payments.refund_task t WHERE t.order_id = f.order_id AND t.status = 'OPEN') AS open_refund,
                    (f.payment_method = 'bank_transfer' AND f.due_minor > 0 AND f.received_minor < f.due_minor
                       AND NOT EXISTS (SELECT 1 FROM payments.payment_request r
                                        WHERE r.order_id = f.order_id AND r.creation_state = 'CREATED' AND r.cancelled_at IS NULL AND r.status <> 'cancelled')) AS instructions_pending
               FROM payments.order_finance f
              WHERE f.order_id = :order AND f.organizer_id = :organizer",
            ['order' => $order->toString(), 'organizer' => $organizer->toString()],
        );
        if (false === $row) {
            throw OrderFinanceNotFound::forId($order);
        }

        $currency = Currency::from(self::str($row, 'currency'));

        return new OrderFinanceView(
            $order,
            $this->view($row),
            Money::of(self::int($row, 'received_transfer_minor'), $currency),
            Money::of(self::int($row, 'received_cash_minor'), $currency),
            Money::of(self::int($row, 'refunded_minor'), $currency),
            self::int($row, 'ledger_version'),
            (bool) $row['open_refund'],
            (bool) $row['instructions_pending'],
        );
    }

    public function summaries(OrganizerId $organizer, array $orders): array
    {
        if ([] === $orders) {
            return [];
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT order_id, currency, state, due_minor, received_minor FROM payments.order_finance WHERE organizer_id = :organizer AND order_id IN (:orders)',
            ['organizer' => $organizer->toString(), 'orders' => array_map(static fn(OrderId $o): string => $o->toString(), $orders)],
            ['orders' => ArrayParameterType::STRING],
        );

        $result = [];
        foreach ($rows as $row) {
            $result[self::str($row, 'order_id')] = $this->view($row);
        }

        return $result;
    }

    public function paymentInstructions(OrganizerId $organizer, OrderId $order): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, purpose, status, variable_symbol, account_number, iban, amount_minor, amount_currency, due_at, spayd, message, known_paid_minor
               FROM payments.payment_request
              WHERE organizer_id = :organizer AND order_id = :order AND creation_state = 'CREATED'
                AND cancelled_at IS NULL AND status IN ('issued', 'open', 'partially_paid')
              ORDER BY attempt",
            ['organizer' => $organizer->toString(), 'order' => $order->toString()],
        );

        $views = [];
        foreach ($rows as $row) {
            $paid = self::int($row, 'known_paid_minor') > 0;
            $views[] = new PaymentInstructionsView(
                PaymentRequestId::fromString(self::str($row, 'id')),
                PaymentRequestPurpose::from(self::str($row, 'purpose')),
                PaymentRequestStatus::from(self::str($row, 'status')),
                self::str($row, 'variable_symbol'),
                self::str($row, 'account_number'),
                \is_string($row['iban']) ? $row['iban'] : null,
                Money::of(self::int($row, 'amount_minor'), Currency::from(self::str($row, 'amount_currency'))),
                new \DateTimeImmutable(self::str($row, 'due_at')),
                // Pairrot refuses instructions once something was paid: no QR, the page shows the remainder and the VS.
                $paid ? '' : self::str($row, 'spayd'),
                self::str($row, 'message'),
            );
        }

        return $views;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function view(array $row): FinancialStateView
    {
        $currency = Currency::from(self::str($row, 'currency'));

        return new FinancialStateView(
            FinancialState::from(self::str($row, 'state')),
            Money::of(self::int($row, 'due_minor'), $currency),
            Money::of(self::int($row, 'received_minor'), $currency),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function str(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        \assert(\is_string($value), $key);

        return $value;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function int(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        \assert(\is_int($value) || \is_string($value), $key);

        return (int) $value;
    }
}
