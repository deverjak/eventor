<?php

declare(strict_types=1);

namespace Eventor\Payments\Infrastructure\Pairrot;

use Doctrine\DBAL\Connection;
use Eventor\Payments\Application\Port\PairrotAccount;
use Eventor\Payments\Application\Port\PairrotAccounts;
use Eventor\Shared\Domain\Id\OrganizerId;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(PairrotAccounts::class)]
final readonly class DbalPairrotAccounts implements PairrotAccounts
{
    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
    ) {}

    public function find(OrganizerId $organizer): ?PairrotAccount
    {
        return $this->fetch('organizer_id = :value', $organizer->toString());
    }

    public function findBySubtenant(string $subtenantId): ?PairrotAccount
    {
        return $this->fetch('subtenant_id = :value', $subtenantId);
    }

    public function saveSubtenant(OrganizerId $organizer, string $subtenantId, string $externalId): void
    {
        $now = $this->now();
        $this->connection->executeStatement(
            'INSERT INTO payments.organizer_pairrot_account (organizer_id, subtenant_id, subtenant_external_id, created_at, updated_at)
             VALUES (:organizer, :subtenant, :external, :created, :updated)
             ON CONFLICT (organizer_id) DO NOTHING',
            ['organizer' => $organizer->toString(), 'subtenant' => $subtenantId, 'external' => $externalId, 'created' => $now, 'updated' => $now],
        );
    }

    public function saveConnection(OrganizerId $organizer, string $bankConnectionId, string $maskedIban, ?string $inboundAddress): void
    {
        $this->connection->executeStatement(
            'UPDATE payments.organizer_pairrot_account
                SET bank_connection_id = :connection, masked_iban = :iban, inbound_address = :inbound, updated_at = :now
              WHERE organizer_id = :organizer',
            ['organizer' => $organizer->toString(), 'connection' => $bankConnectionId, 'iban' => $maskedIban, 'inbound' => $inboundAddress, 'now' => $this->now()],
        );
    }

    private function fetch(string $where, string $value): ?PairrotAccount
    {
        /** @var array<string, string|null>|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT organizer_id, subtenant_id, subtenant_external_id, bank_connection_id, masked_iban, inbound_address
               FROM payments.organizer_pairrot_account WHERE '.$where,
            ['value' => $value],
        );
        if (false === $row) {
            return null;
        }

        return new PairrotAccount(
            OrganizerId::fromString((string) $row['organizer_id']),
            (string) $row['subtenant_id'],
            (string) $row['subtenant_external_id'],
            $row['bank_connection_id'],
            $row['masked_iban'],
            $row['inbound_address'],
        );
    }

    private function now(): string
    {
        return \DateTimeImmutable::createFromInterface($this->clock->now())->format('Y-m-d H:i:sP');
    }
}
