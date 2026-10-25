<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Payments\Application\Query\View\RefundTaskView;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * The manual refund queue (PAY-10, PAY-11), newest first.
 */
final readonly class RefundTaskQuery
{
    public function __construct(private Connection $connection) {}

    /**
     * @param 'open'|'settled'|'cancelled'|null $status
     *
     * @return list<RefundTaskView>
     */
    public function list(OrganizerId $organizer, ?string $status): array
    {
        $sql = 'SELECT * FROM payments.refund_task WHERE organizer_id = :organizer';
        $params = ['organizer' => $organizer->toString()];
        if (null !== $status) {
            $sql .= ' AND status = :status';
            $params['status'] = strtoupper($status);
        }

        return array_values(array_map(static fn(array $t): RefundTaskView => new RefundTaskView(
            RowReader::string($t['id']),
            RowReader::string($t['order_id']),
            RowReader::money($t['amount_minor'], $t['amount_currency']),
            RowReader::string($t['reason']),
            RowReader::string($t['status']),
            RowReader::nullableString($t['note']),
            RowReader::time($t['created_at']),
            RowReader::time($t['settled_at']),
            RowReader::nullableString($t['settle_method']),
            RowReader::nullableString($t['settle_reference']),
            RowReader::time($t['cancelled_at']),
        ), $this->connection->fetchAllAssociative($sql.' ORDER BY created_at DESC, id', $params)));
    }
}
