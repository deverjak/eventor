<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Architecture;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * The migrations are the contract of the database: from an empty database they migrate up, leave exactly the grants
 * ADR 013 asks for (append-only tables included), go all the way down and up again. Runs the real
 * `doctrine:migrations:migrate` in child processes against a throw-away database `eventor_test_<token>mig`.
 */
final class MigrationsGrantTest extends TestCase
{
    private string $token;
    private string $database;

    protected function setUp(): void
    {
        $base = $_SERVER['TEST_TOKEN'] ?? 'default';
        $this->token = (\is_string($base) ? $base : 'default').'mig';
        $this->database = 'eventor_test_'.$this->token;

        $admin = $this->connect('postgres', 'DATABASE_MIGRATIONS_URL');
        $admin->executeStatement(\sprintf('DROP DATABASE IF EXISTS "%s" WITH (FORCE)', $this->database));
        $admin->executeStatement(\sprintf('CREATE DATABASE "%s" OWNER eventor_owner', $this->database));
        $admin->close();

        // The version table lives in `shared`, so the schema must exist before the first migration (bin/test-db does the same).
        $owner = $this->connect($this->database, 'DATABASE_MIGRATIONS_URL');
        $owner->executeStatement('CREATE SCHEMA shared AUTHORIZATION eventor_owner');
        $owner->close();
    }

    protected function tearDown(): void
    {
        $admin = $this->connect('postgres', 'DATABASE_MIGRATIONS_URL');
        $admin->executeStatement(\sprintf('DROP DATABASE IF EXISTS "%s" WITH (FORCE)', $this->database));
        $admin->close();
    }

    public function test_migrations_go_up_down_and_up_again_and_leave_the_right_grants(): void
    {
        $this->migrate('latest');
        $this->assertGrants();

        $this->migrate('first');
        $app = $this->connect($this->database, 'DATABASE_URL');
        $left = $app->fetchFirstColumn("SELECT schema_name FROM information_schema.schemata WHERE schema_name NOT IN ('information_schema', 'public', 'shared') AND schema_name NOT LIKE 'pg_%'");
        self::assertSame([], $left, 'Going down must remove every module schema.');
        $app->close();

        $this->migrate('latest');
        $this->assertGrants();
    }

    private function assertGrants(): void
    {
        $app = $this->connect($this->database, 'DATABASE_URL');

        self::assertSame('eventor_app', $app->fetchOne('SELECT current_user'));
        self::assertSame('shared', $app->fetchOne('SHOW search_path'));

        foreach (ModuleCatalog::SCHEMAS as $schema) {
            self::assertTrue((bool) $app->fetchOne("SELECT has_schema_privilege('eventor_app', ?, 'USAGE')", [$schema]), "USAGE on $schema");
        }

        $existing = 0;
        foreach (ModuleCatalog::APPEND_ONLY_TABLES as $table) {
            if (null === $app->fetchOne('SELECT to_regclass(?)', [$table])) {
                continue;
            }
            ++$existing;
            self::assertTrue((bool) $app->fetchOne("SELECT has_table_privilege('eventor_app', ?, 'INSERT')", [$table]), "INSERT on $table");
            self::assertTrue((bool) $app->fetchOne("SELECT has_table_privilege('eventor_app', ?, 'SELECT')", [$table]), "SELECT on $table");
            foreach (['UPDATE', 'DELETE', 'TRUNCATE'] as $privilege) {
                self::assertFalse((bool) $app->fetchOne("SELECT has_table_privilege('eventor_app', ?, ?)", [$table, $privilege]), "$privilege on $table must be revoked");
            }
        }
        self::assertGreaterThanOrEqual(1, $existing);

        // Every table of every module schema (except append-only ones) is writable by the application.
        $tables = $app->fetchFirstColumn(
            "SELECT table_schema || '.' || table_name FROM information_schema.tables
              WHERE table_type = 'BASE TABLE' AND table_schema IN ('".implode("','", array_diff(ModuleCatalog::SCHEMAS, ['reporting']))."')",
        );
        self::assertNotEmpty($tables);
        foreach ($tables as $table) {
            \assert(\is_string($table));
            if (\in_array($table, ModuleCatalog::APPEND_ONLY_TABLES, true) || isset(ModuleCatalog::PARTIALLY_UPDATABLE_TABLES[$table]) || 'shared.doctrine_migration_versions' === $table) {
                continue;
            }
            foreach (['SELECT', 'INSERT', 'UPDATE', 'DELETE'] as $privilege) {
                self::assertTrue((bool) $app->fetchOne("SELECT has_table_privilege('eventor_app', ?, ?)", [$table, $privilege]), "$privilege on $table");
            }
        }

        // The application cannot change the schema.
        $app->close();
    }

    private function migrate(string $target): void
    {
        $process = new Process(
            [\PHP_BINARY, 'bin/console', 'doctrine:migrations:migrate', $target, '--no-interaction', '--env=test'],
            ModuleCatalog::projectDir(),
            ['TEST_TOKEN' => $this->token, 'APP_ENV' => 'test', 'SHELL_VERBOSITY' => '0'],
            timeout: 120,
        );
        $process->run();

        self::assertTrue($process->isSuccessful(), "migrate $target failed:\n".$process->getOutput().$process->getErrorOutput());
    }

    private function connect(string $database, string $urlEnv): Connection
    {
        $url = $_SERVER[$urlEnv] ?? $_ENV[$urlEnv] ?? getenv($urlEnv);
        self::assertIsString($url, "$urlEnv must be set (compose sets it).");

        $params = (new DsnParser(['postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql']))->parse($url);
        $params['dbname'] = $database;

        return DriverManager::getConnection($params);
    }
}
