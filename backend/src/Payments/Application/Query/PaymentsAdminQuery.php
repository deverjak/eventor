<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Payments\Contract\Exception\OrderFinanceNotFound;
use Eventor\Payments\Domain\Unmatched\UnmatchedTransactionNotFound;
use Eventor\Shared\Domain\Id\BankTransactionId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Read models of the admin payments screens (order finance, ledger, refund tasks, reconciliation queue). Every query
 * names the organizer: Doctrine filters do not apply to DBAL (ADR 005). Money is `{minor, currency}`, times ISO 8601.
 */
final readonly class PaymentsAdminQuery
{
    public function __construct(private Connection $connection) {}

    /**
     * @return array<string, mixed>
     */
    public function orderFinance(OrganizerId $organizer, OrderId $order): array
    {
        $finance = $this->connection->fetchAssociative(
            'SELECT * FROM payments.order_finance WHERE order_id = :order AND organizer_id = :organizer',
            ['order' => $order->toString(), 'organizer' => $organizer->toString()],
        );
        if (false === $finance) {
            throw OrderFinanceNotFound::forId($order);
        }
        $currency = self::s($finance['currency']);

        $requests = array_map(fn(array $r): array => [
            'id' => $r['id'],
            'attempt' => self::i($r['attempt']),
            'purpose' => $r['purpose'],
            'externalId' => $r['external_id'],
            'creationState' => $r['creation_state'],
            'status' => $r['status'],
            'amount' => $this->money($r['amount_minor'], $r['amount_currency']),
            'knownPaid' => $this->money($r['known_paid_minor'], $r['amount_currency']),
            'variableSymbol' => $r['variable_symbol'],
            'dueAt' => $this->time($r['due_at']),
            'failedAttempts' => self::i($r['failed_attempts']),
            'lastError' => $r['last_error'],
            'issuedAt' => $this->time($r['issued_at']),
            'cancelledAt' => $this->time($r['cancelled_at']),
        ], $this->connection->fetchAllAssociative(
            'SELECT * FROM payments.payment_request WHERE order_id = :order AND organizer_id = :organizer ORDER BY attempt',
            ['order' => $order->toString(), 'organizer' => $organizer->toString()],
        ));

        $refund = $this->connection->fetchAssociative(
            "SELECT id, amount_minor, amount_currency, reason FROM payments.refund_task WHERE order_id = :order AND organizer_id = :organizer AND status = 'OPEN'",
            ['order' => $order->toString(), 'organizer' => $organizer->toString()],
        );

        return [
            'orderId' => $order->toString(),
            'state' => $finance['state'],
            'paymentMethod' => $finance['payment_method'],
            'amountDue' => $this->money($finance['due_minor'], $currency),
            'received' => $this->money($finance['received_minor'], $currency),
            'receivedTransfer' => $this->money($finance['received_transfer_minor'], $currency),
            'receivedCash' => $this->money($finance['received_cash_minor'], $currency),
            'refunded' => $this->money($finance['refunded_minor'], $currency),
            'outstanding' => $this->money(max(0, self::i($finance['due_minor']) - self::i($finance['received_minor'])), $currency),
            'disputed' => self::b($finance['disputed']),
            'ledgerVersion' => self::i($finance['ledger_version']),
            'updatedAt' => $this->time($finance['updated_at']),
            'paymentRequests' => $requests,
            'openRefundTask' => false === $refund ? null : [
                'id' => $refund['id'],
                'amount' => $this->money($refund['amount_minor'], $refund['amount_currency']),
                'reason' => $refund['reason'],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function ledger(OrganizerId $organizer, OrderId $order): array
    {
        $exists = $this->connection->fetchOne(
            'SELECT 1 FROM payments.order_finance WHERE order_id = :order AND organizer_id = :organizer',
            ['order' => $order->toString(), 'organizer' => $organizer->toString()],
        );
        if (false === $exists) {
            throw OrderFinanceNotFound::forId($order);
        }

        return array_map(fn(array $e): array => [
            'id' => $e['id'],
            'kind' => $e['kind'],
            'amount' => $this->money($e['amount_minor'], $e['amount_currency']),
            'source' => $e['source'],
            'compensatesId' => $e['compensates_id'],
            'paymentRequestId' => $e['payment_request_id'],
            'stationId' => $e['station_id'],
            'actor' => json_decode(self::s($e['actor']), true),
            'reason' => $e['reason'],
            'occurredAt' => $this->time($e['occurred_at']),
            'recordedAt' => $this->time($e['recorded_at']),
        ], $this->connection->fetchAllAssociative(
            'SELECT * FROM payments.ledger_entry WHERE order_id = :order AND organizer_id = :organizer ORDER BY recorded_at, id',
            ['order' => $order->toString(), 'organizer' => $organizer->toString()],
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function refundTasks(OrganizerId $organizer, ?string $status): array
    {
        $sql = 'SELECT * FROM payments.refund_task WHERE organizer_id = :organizer';
        $params = ['organizer' => $organizer->toString()];
        if (null !== $status) {
            $sql .= ' AND status = :status';
            $params['status'] = strtoupper($status);
        }

        return array_map(fn(array $t): array => [
            'id' => $t['id'],
            'orderId' => $t['order_id'],
            'amount' => $this->money($t['amount_minor'], $t['amount_currency']),
            'reason' => $t['reason'],
            'status' => $t['status'],
            'note' => $t['note'],
            'createdAt' => $this->time($t['created_at']),
            'settledAt' => $this->time($t['settled_at']),
            'settleMethod' => $t['settle_method'],
            'settleReference' => $t['settle_reference'],
            'cancelledAt' => $this->time($t['cancelled_at']),
        ], $this->connection->fetchAllAssociative($sql.' ORDER BY created_at DESC, id', $params));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function unmatchedTransactions(OrganizerId $organizer, bool $onlyOpen): array
    {
        return array_map(fn(array $t): array => $this->unmatched($t), $this->connection->fetchAllAssociative(
            'SELECT * FROM payments.unmatched_transaction WHERE organizer_id = :organizer'.($onlyOpen ? ' AND resolved_at IS NULL' : '').' ORDER BY booked_on DESC, created_at DESC',
            ['organizer' => $organizer->toString()],
        ));
    }

    /**
     * Orders that could own an unmatched transaction: an open request with the same variable symbol (leading zeros
     * ignored), or the same outstanding amount. Pairrot's own suggestion comes first.
     *
     * @return list<array<string, mixed>>
     */
    public function candidates(OrganizerId $organizer, BankTransactionId $transaction): array
    {
        $tx = $this->connection->fetchAssociative(
            'SELECT amount_minor, variable_symbol, suggested_request_pairrot_id FROM payments.unmatched_transaction WHERE id = :id AND organizer_id = :organizer',
            ['id' => $transaction->toString(), 'organizer' => $organizer->toString()],
        );
        if (false === $tx) {
            throw UnmatchedTransactionNotFound::forId($transaction->toString());
        }

        $vs = null === $tx['variable_symbol'] ? null : ltrim(self::s($tx['variable_symbol']), '0');
        $rows = $this->connection->fetchAllAssociative(
            "SELECT r.id, r.order_id, r.variable_symbol, r.amount_minor, r.amount_currency, r.known_paid_minor, r.status, r.pairrot_id, f.state
               FROM payments.payment_request r
               JOIN payments.order_finance f ON f.order_id = r.order_id
              WHERE r.organizer_id = :organizer AND r.creation_state = 'CREATED' AND r.cancelled_at IS NULL
                AND ( r.pairrot_id = :suggested
                      OR (CAST(:vs AS text) IS NOT NULL AND ltrim(r.variable_symbol, '0') = CAST(:vs2 AS text))
                      OR r.amount_minor - r.known_paid_minor = :amount )
              ORDER BY (r.pairrot_id = :suggested2) DESC, r.created_at DESC
              LIMIT 20",
            [
                'organizer' => $organizer->toString(),
                'suggested' => $tx['suggested_request_pairrot_id'] ?? '', 'suggested2' => $tx['suggested_request_pairrot_id'] ?? '',
                'vs' => $vs,
                'vs2' => $vs,
                'amount' => self::i($tx['amount_minor']),
            ],
        );

        return array_map(fn(array $r): array => [
            'paymentRequestId' => $r['id'],
            'orderId' => $r['order_id'],
            'variableSymbol' => $r['variable_symbol'],
            'amount' => $this->money($r['amount_minor'], $r['amount_currency']),
            'outstanding' => $this->money(max(0, self::i($r['amount_minor']) - self::i($r['known_paid_minor'])), $r['amount_currency']),
            'requestStatus' => $r['status'],
            'financialState' => $r['state'],
            'reasons' => array_values(array_filter([
                $r['pairrot_id'] === $tx['suggested_request_pairrot_id'] ? 'suggested_by_pairrot' : null,
                null !== $vs && ltrim(self::s($r['variable_symbol']), '0') === $vs ? 'same_variable_symbol' : null,
                self::i($r['amount_minor']) - self::i($r['known_paid_minor']) === self::i($tx['amount_minor']) ? 'same_amount' : null,
            ])),
        ], $rows);
    }

    /**
     * The Pairrot mapping of the organizer (ids and a masked IBAN, never keys).
     *
     * @return array<string, mixed>|null
     */
    public function connection(OrganizerId $organizer): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT subtenant_id, bank_connection_id, masked_iban, inbound_address, updated_at FROM payments.organizer_pairrot_account WHERE organizer_id = :organizer',
            ['organizer' => $organizer->toString()],
        );

        return false === $row ? null : [
            'subtenantId' => $row['subtenant_id'],
            'bankConnectionId' => $row['bank_connection_id'],
            'maskedIban' => $row['masked_iban'],
            'inboundAddress' => $row['inbound_address'],
            'updatedAt' => $this->time($row['updated_at']),
        ];
    }

    /**
     * @param array<string, mixed> $t
     *
     * @return array<string, mixed>
     */
    private function unmatched(array $t): array
    {
        return [
            'id' => $t['id'],
            'amount' => $this->money($t['amount_minor'], $t['amount_currency']),
            'variableSymbol' => $t['variable_symbol'],
            'bookedOn' => $t['booked_on'],
            'matchingResult' => $t['matching_result'],
            'handlingState' => $t['handling_state'],
            'createdAt' => $this->time($t['created_at']),
            'resolvedAt' => $this->time($t['resolved_at']),
            'resolution' => $t['resolution'],
            'resolvedOrderId' => $t['resolved_order_id'],
            'allocationReason' => $t['allocation_reason'],
        ];
    }

    /**
     * @return array{minor: int, currency: string}
     */
    private function money(mixed $minor, mixed $currency): array
    {
        return ['minor' => self::i($minor), 'currency' => self::s($currency)];
    }

    private static function i(mixed $v): int
    {
        return \is_int($v) || \is_string($v) || \is_float($v) ? (int) $v : 0;
    }

    private static function s(mixed $v): string
    {
        return \is_scalar($v) ? (string) $v : '';
    }

    private static function b(mixed $v): bool
    {
        return true === $v || 't' === $v || 1 === $v || '1' === $v;
    }

    private function time(mixed $value): ?string
    {
        return \is_string($value) ? (new \DateTimeImmutable($value))->format(\DATE_ATOM) : null;
    }
}
