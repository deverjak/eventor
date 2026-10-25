<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Payments\Application\Query\View\LedgerEntryView;
use Eventor\Payments\Application\Query\View\OpenRefundView;
use Eventor\Payments\Application\Query\View\OrderFinanceDetailView;
use Eventor\Payments\Application\Query\View\PaymentRequestView;
use Eventor\Payments\Contract\Exception\OrderFinanceNotFound;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * The finance screen of one order (state, split, payment requests, open refund task) and its ledger. Every query
 * names the organizer: Doctrine filters do not apply to DBAL (ADR 005).
 */
final readonly class OrderFinanceDetailQuery
{
    public function __construct(private Connection $connection) {}

    public function detail(OrganizerId $organizer, OrderId $order): OrderFinanceDetailView
    {
        $params = ['order' => $order->toString(), 'organizer' => $organizer->toString()];
        $finance = $this->connection->fetchAssociative('SELECT * FROM payments.order_finance WHERE order_id = :order AND organizer_id = :organizer', $params);
        if (false === $finance) {
            throw OrderFinanceNotFound::forId($order);
        }
        $currency = $finance['currency'];

        $requests = array_map(static fn(array $r): PaymentRequestView => new PaymentRequestView(
            RowReader::string($r['id']),
            RowReader::int($r['attempt']),
            RowReader::string($r['purpose']),
            RowReader::string($r['external_id']),
            RowReader::string($r['creation_state']),
            RowReader::string($r['status']),
            RowReader::money($r['amount_minor'], $r['amount_currency']),
            RowReader::money($r['known_paid_minor'], $r['amount_currency']),
            RowReader::nullableString($r['variable_symbol']),
            RowReader::time($r['due_at']),
            RowReader::int($r['failed_attempts']),
            RowReader::nullableString($r['last_error']),
            RowReader::time($r['issued_at']),
            RowReader::time($r['cancelled_at']),
        ), $this->connection->fetchAllAssociative('SELECT * FROM payments.payment_request WHERE order_id = :order AND organizer_id = :organizer ORDER BY attempt', $params));

        $refund = $this->connection->fetchAssociative(
            "SELECT id, amount_minor, amount_currency, reason FROM payments.refund_task WHERE order_id = :order AND organizer_id = :organizer AND status = 'OPEN'",
            $params,
        );

        return new OrderFinanceDetailView(
            $order->toString(),
            RowReader::string($finance['state']),
            RowReader::string($finance['payment_method']),
            RowReader::money($finance['due_minor'], $currency),
            RowReader::money($finance['received_minor'], $currency),
            RowReader::money($finance['received_transfer_minor'], $currency),
            RowReader::money($finance['received_cash_minor'], $currency),
            RowReader::money($finance['refunded_minor'], $currency),
            RowReader::money(max(0, RowReader::int($finance['due_minor']) - RowReader::int($finance['received_minor'])), $currency),
            RowReader::bool($finance['disputed']),
            RowReader::int($finance['ledger_version']),
            RowReader::time($finance['updated_at']),
            array_values($requests),
            false === $refund ? null : new OpenRefundView(RowReader::string($refund['id']), RowReader::money($refund['amount_minor'], $refund['amount_currency']), RowReader::string($refund['reason'])),
        );
    }

    /**
     * @return list<LedgerEntryView> oldest first
     */
    public function ledger(OrganizerId $organizer, OrderId $order): array
    {
        $params = ['order' => $order->toString(), 'organizer' => $organizer->toString()];
        if (false === $this->connection->fetchOne('SELECT 1 FROM payments.order_finance WHERE order_id = :order AND organizer_id = :organizer', $params)) {
            throw OrderFinanceNotFound::forId($order);
        }

        return array_values(array_map(static function (array $e): LedgerEntryView {
            $actor = json_decode(RowReader::string($e['actor']), true);
            /** @var array{type: string, id: string|null, label: string|null}|null $actor */
            $actor = \is_array($actor) ? $actor : null;

            return new LedgerEntryView(
                RowReader::string($e['id']),
                RowReader::string($e['kind']),
                RowReader::money($e['amount_minor'], $e['amount_currency']),
                RowReader::string($e['source']),
                RowReader::nullableString($e['compensates_id']),
                RowReader::nullableString($e['payment_request_id']),
                RowReader::nullableString($e['station_id']),
                $actor,
                RowReader::nullableString($e['reason']),
                RowReader::time($e['occurred_at']),
                RowReader::time($e['recorded_at']),
            );
        }, $this->connection->fetchAllAssociative('SELECT * FROM payments.ledger_entry WHERE order_id = :order AND organizer_id = :organizer ORDER BY recorded_at, id', $params)));
    }
}
