<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Symfony;

use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

/**
 * Builds the Postgres session handler (`shared.sessions`) on its own PDO connection from DATABASE_URL, so its
 * session-row lock never interferes with Doctrine's transactions.
 */
final class SessionHandlerFactory
{
    public static function create(string $databaseUrl): PdoSessionHandler
    {
        $parts = parse_url($databaseUrl);
        if (false === $parts || !isset($parts['host'])) {
            throw new \InvalidArgumentException('DATABASE_URL is not a valid database URL.');
        }

        $dsn = \sprintf(
            'pgsql:host=%s;port=%d;dbname=%s',
            $parts['host'],
            $parts['port'] ?? 5432,
            ltrim($parts['path'] ?? '/eventor', '/'),
        );

        return new PdoSessionHandler($dsn, [
            'db_table' => 'shared.sessions',
            'db_username' => isset($parts['user']) ? rawurldecode($parts['user']) : null,
            'db_password' => isset($parts['pass']) ? rawurldecode($parts['pass']) : null,
            'lock_mode' => PdoSessionHandler::LOCK_TRANSACTIONAL,
        ]);
    }
}
