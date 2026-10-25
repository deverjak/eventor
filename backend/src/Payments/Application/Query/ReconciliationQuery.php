<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Payments\Application\Query\View\AllocationCandidateView;
use Eventor\Payments\Application\Query\View\UnmatchedTransactionView;
use Eventor\Payments\Domain\Unmatched\UnmatchedTransactionNotFound;
use Eventor\Shared\Domain\Id\BankTransactionId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * The reconciliation screen (PAY-06): the queue of unmatched bank transactions and the candidates for one of them.
 */
final readonly class ReconciliationQuery
{
    public function __construct(private Connection $connection) {}

    /**
     * @return list<UnmatchedTransactionView>
     */
    public function unmatched(OrganizerId $organizer, bool $onlyOpen): array
    {
        return array_values(array_map(static fn(array $t): UnmatchedTransactionView => new UnmatchedTransactionView(
            RowReader::string($t['id']),
            RowReader::money($t['amount_minor'], $t['amount_currency']),
            RowReader::nullableString($t['variable_symbol']),
            RowReader::string($t['booked_on']),
            RowReader::string($t['matching_result']),
            RowReader::string($t['handling_state']),
            RowReader::time($t['created_at']),
            RowReader::time($t['resolved_at']),
            RowReader::nullableString($t['resolution']),
            RowReader::nullableString($t['resolved_order_id']),
            RowReader::nullableString($t['allocation_reason']),
        ), $this->connection->fetchAllAssociative(
            'SELECT * FROM payments.unmatched_transaction WHERE organizer_id = :organizer'.($onlyOpen ? ' AND resolved_at IS NULL' : '').' ORDER BY booked_on DESC, created_at DESC',
            ['organizer' => $organizer->toString()],
        )));
    }

    /**
     * Orders that could own an unmatched transaction: an open request with the same variable symbol (leading zeros
     * ignored), or the same outstanding amount. Pairrot's own suggestion comes first.
     *
     * @return list<AllocationCandidateView>
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

        $vs = null === $tx['variable_symbol'] ? null : ltrim(RowReader::string($tx['variable_symbol']), '0');
        $suggested = RowReader::nullableString($tx['suggested_request_pairrot_id']);
        $amount = RowReader::int($tx['amount_minor']);
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
            ['organizer' => $organizer->toString(), 'suggested' => $suggested ?? '', 'suggested2' => $suggested ?? '', 'vs' => $vs, 'vs2' => $vs, 'amount' => $amount],
        );

        return array_values(array_map(static function (array $r) use ($vs, $suggested, $amount): AllocationCandidateView {
            $outstanding = max(0, RowReader::int($r['amount_minor']) - RowReader::int($r['known_paid_minor']));

            return new AllocationCandidateView(
                RowReader::string($r['id']),
                RowReader::string($r['order_id']),
                RowReader::nullableString($r['variable_symbol']),
                RowReader::money($r['amount_minor'], $r['amount_currency']),
                RowReader::money($outstanding, $r['amount_currency']),
                RowReader::string($r['status']),
                RowReader::string($r['state']),
                array_values(array_filter([
                    null !== $suggested && RowReader::nullableString($r['pairrot_id']) === $suggested ? 'suggested_by_pairrot' : null,
                    null !== $vs && ltrim(RowReader::string($r['variable_symbol']), '0') === $vs ? 'same_variable_symbol' : null,
                    $outstanding === $amount ? 'same_amount' : null,
                ])),
            );
        }, $rows));
    }
}
