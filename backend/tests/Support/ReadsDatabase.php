<?php

declare(strict_types=1);

namespace Eventor\Tests\Support;

use Doctrine\DBAL\Connection;

/**
 * Typed one-liners for assertions against the test database (fetchOne() returns mixed).
 */
trait ReadsDatabase
{
    abstract protected function connection(): Connection;

    /**
     * @param list<mixed>|array<string, mixed> $params
     */
    protected function dbInt(string $sql, array $params = []): int
    {
        $value = $this->connection()->fetchOne($sql, $params);
        if (!\is_int($value) && !(\is_string($value) && is_numeric($value))) {
            throw new \UnexpectedValueException('Expected an integer, got '.get_debug_type($value).': '.$sql);
        }

        return (int) $value;
    }

    /**
     * @param list<mixed>|array<string, mixed> $params
     */
    protected function dbFloat(string $sql, array $params = []): float
    {
        $value = $this->connection()->fetchOne($sql, $params);
        if (!is_numeric($value)) {
            throw new \UnexpectedValueException('Expected a number, got '.get_debug_type($value).': '.$sql);
        }

        return (float) $value;
    }

    /**
     * @param list<mixed>|array<string, mixed> $params
     */
    protected function dbString(string $sql, array $params = []): string
    {
        $value = $this->connection()->fetchOne($sql, $params);
        if (!\is_string($value)) {
            throw new \UnexpectedValueException('Expected a string, got '.get_debug_type($value).': '.$sql);
        }

        return $value;
    }

    /**
     * @param list<mixed>|array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    protected function dbRow(string $sql, array $params = []): array
    {
        $row = $this->connection()->fetchAssociative($sql, $params);
        if (false === $row) {
            throw new \UnexpectedValueException('No row: '.$sql);
        }

        return $row;
    }

    /**
     * @param list<mixed>|array<string, mixed> $params
     *
     * @return list<array<string, mixed>>
     */
    protected function dbRows(string $sql, array $params = []): array
    {
        return $this->connection()->fetchAllAssociative($sql, $params);
    }

    /**
     * @param list<mixed>|array<string, mixed> $params
     *
     * @return list<mixed>
     */
    protected function dbColumn(string $sql, array $params = []): array
    {
        return $this->connection()->fetchFirstColumn($sql, $params);
    }
}
