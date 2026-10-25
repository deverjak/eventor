<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Idempotency;

use Doctrine\DBAL\Connection;
use Eventor\Shared\Application\Idempotency\IdempotencyKeyReused;
use Eventor\Shared\Application\Idempotency\IdempotencyStore;
use Eventor\Shared\Application\Idempotency\RequestInProgress;
use Eventor\Shared\Application\Idempotency\StoredResponse;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * `shared.idempotency_record`. Each call is a single autocommit statement (the middleware runs outside the command
 * transaction), so a concurrent duplicate sees `in_progress` immediately.
 */
final readonly class DbalIdempotencyStore implements IdempotencyStore
{
    private const string TTL = 'PT24H';

    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
        private ?LoggerInterface $logger = null,
    ) {}

    public function begin(string $scope, string $key, string $requestHash): ?StoredResponse
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        for ($attempt = 0; $attempt < 3; ++$attempt) {
            $this->connection->executeStatement(
                'DELETE FROM shared.idempotency_record WHERE scope = :scope AND idempotency_key = :key AND expires_at < :now',
                ['scope' => $scope, 'key' => $key, 'now' => $now->format('Y-m-d H:i:sP')],
            );

            $inserted = $this->connection->executeStatement(
                'INSERT INTO shared.idempotency_record (scope, idempotency_key, request_hash, status, created_at, expires_at)
                 VALUES (:scope, :key, :hash, \'in_progress\', :now, :expires)
                 ON CONFLICT (scope, idempotency_key) DO NOTHING',
                [
                    'scope' => $scope,
                    'key' => $key,
                    'hash' => $requestHash,
                    'now' => $now->format('Y-m-d H:i:sP'),
                    'expires' => $now->add(new \DateInterval(self::TTL))->format('Y-m-d H:i:sP'),
                ],
            );
            if (1 === $inserted) {
                return null;
            }

            /** @var array{request_hash: string, status: string, response: string|null}|false $row */
            $row = $this->connection->fetchAssociative(
                'SELECT request_hash, status, response FROM shared.idempotency_record WHERE scope = :scope AND idempotency_key = :key',
                ['scope' => $scope, 'key' => $key],
            );
            if (false === $row) {
                continue; // the competing request failed and freed the key between our two statements
            }

            if (!hash_equals($row['request_hash'], $requestHash)) {
                throw IdempotencyKeyReused::create();
            }
            if ('completed' !== $row['status']) {
                throw RequestInProgress::create();
            }

            /** @var array<array-key, mixed>|scalar|null $response */
            $response = null === $row['response'] ? null : json_decode($row['response'], true, 512, \JSON_THROW_ON_ERROR);

            return new StoredResponse($response);
        }

        throw RequestInProgress::create();
    }

    public function complete(string $scope, string $key, mixed $response): void
    {
        try {
            $encoded = json_encode($response, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger?->warning('Idempotent command result is not JSON-serialisable; replays will return null.', ['exception' => $e]);
            $encoded = 'null';
        }

        $this->connection->executeStatement(
            'UPDATE shared.idempotency_record SET status = \'completed\', response = CAST(:response AS json)
              WHERE scope = :scope AND idempotency_key = :key',
            ['response' => $encoded, 'scope' => $scope, 'key' => $key],
        );
    }

    public function abandon(string $scope, string $key): void
    {
        $this->connection->executeStatement(
            'DELETE FROM shared.idempotency_record WHERE scope = :scope AND idempotency_key = :key AND status = \'in_progress\'',
            ['scope' => $scope, 'key' => $key],
        );
    }
}
