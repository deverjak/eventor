<?php

declare(strict_types=1);

namespace Eventor\Registration\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Eventor\Registration\Domain\Order\OrderNumbers;
use Eventor\Shared\Domain\Id\OrganizerId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * One atomic UPSERT per number: concurrent orders of one organizer serialise on the counter row for the length of
 * their transaction, which is short and also makes the numbers gap-free (a rolled-back order releases its number).
 */
#[AsAlias(OrderNumbers::class)]
final readonly class DbalOrderNumbers implements OrderNumbers
{
    public function __construct(private Connection $connection) {}

    public function next(OrganizerId $organizer, \DateTimeImmutable $now): string
    {
        $year = (int) $now->format('Y');
        $value = $this->connection->fetchOne(
            'INSERT INTO registration.order_counter (organizer_id, year, last_value) VALUES (:organizer, :year, 1)
             ON CONFLICT (organizer_id, year) DO UPDATE SET last_value = registration.order_counter.last_value + 1
             RETURNING last_value',
            ['organizer' => $organizer->toString(), 'year' => $year],
        );

        return \sprintf('EV-%d-%06d', $year, is_numeric($value) ? (int) $value : 1);
    }
}
