<?php

declare(strict_types=1);

namespace Eventor\Payments\Infrastructure\Pairrot;

use Doctrine\DBAL\Connection;
use Eventor\Payments\Application\Port\InboxStore;
use Eventor\Shared\Domain\Id\OrganizerId;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\Uid\Uuid;

#[AsAlias(InboxStore::class)]
final readonly class DbalInboxStore implements InboxStore
{
    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
    ) {}

    public function receive(string $messageId, string $type, array $payload, string $status = 'pending'): ?string
    {
        $id = Uuid::v7()->toRfc4122();
        $now = \DateTimeImmutable::createFromInterface($this->clock->now())->format('Y-m-d H:i:s.uP');
        $inserted = $this->connection->executeStatement(
            'INSERT INTO payments.inbox_message (id, provider, message_id, type, payload, status, received_at, processed_at)
             VALUES (:id, :provider, :message, :type, CAST(:payload AS jsonb), :status, :received, :processed)
             ON CONFLICT (provider, message_id) DO NOTHING',
            [
                'id' => $id,
                'provider' => self::PROVIDER,
                'message' => $messageId,
                'type' => $type,
                'payload' => json_encode($payload, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES),
                'status' => $status,
                'received' => $now,
                'processed' => 'pending' === $status ? null : $now,
            ],
        );

        return $inserted > 0 ? $id : null;
    }

    public function peek(string $inboxId): ?array
    {
        /** @var array{type: string, status: string, payload: string}|false $row */
        $row = $this->connection->fetchAssociative('SELECT type, status, payload FROM payments.inbox_message WHERE id = :id', ['id' => $inboxId]);
        if (false === $row) {
            return null;
        }

        $payload = json_decode($row['payload'], true, 512, \JSON_THROW_ON_ERROR);

        /** @var array<string, mixed> $decoded */
        $decoded = \is_array($payload) ? $payload : [];

        return ['type' => $row['type'], 'status' => $row['status'], 'payload' => $decoded];
    }

    public function organizerOfRequest(string $pairrotRequestId): ?OrganizerId
    {
        $organizer = $this->connection->fetchOne('SELECT organizer_id FROM payments.payment_request WHERE pairrot_id = :id', ['id' => $pairrotRequestId]);

        return \is_string($organizer) ? OrganizerId::fromString($organizer) : null;
    }
}
