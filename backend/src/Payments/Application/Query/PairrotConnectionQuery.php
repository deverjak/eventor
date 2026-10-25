<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Payments\Application\Query\View\PairrotConnectionView;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Whether the organizer is connected to Pairrot (ORG-02): ids and a masked IBAN only.
 */
final readonly class PairrotConnectionQuery
{
    public function __construct(private Connection $connection) {}

    public function forOrganizer(OrganizerId $organizer): ?PairrotConnectionView
    {
        $row = $this->connection->fetchAssociative(
            'SELECT subtenant_id, bank_connection_id, masked_iban, inbound_address, updated_at FROM payments.organizer_pairrot_account WHERE organizer_id = :organizer',
            ['organizer' => $organizer->toString()],
        );

        return false === $row ? null : new PairrotConnectionView(
            RowReader::string($row['subtenant_id']),
            RowReader::nullableString($row['bank_connection_id']),
            RowReader::nullableString($row['masked_iban']),
            RowReader::nullableString($row['inbound_address']),
            RowReader::time($row['updated_at']),
        );
    }
}
