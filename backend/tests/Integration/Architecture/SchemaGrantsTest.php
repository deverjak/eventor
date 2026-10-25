<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Architecture;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Eventor\Tests\Support\IntegrationTestCase;

/**
 * ADR 013 / chapter 03: the application connects as `eventor_app`: DML only, no DDL, search_path = shared, append-only
 * tables are protected by grants. The migrations run as `eventor_owner` (a different role).
 */
final class SchemaGrantsTest extends IntegrationTestCase
{
    public function test_the_application_does_not_run_as_the_schema_owner(): void
    {
        self::assertSame('eventor_app', $this->connection()->fetchOne('SELECT current_user'));
        self::assertSame('eventor_owner', $this->owner()->fetchOne('SELECT current_user'));
        self::assertSame('eventor_owner', $this->connection()->fetchOne("SELECT schema_owner FROM information_schema.schemata WHERE schema_name = 'identity'"));
    }

    public function test_the_search_path_contains_only_shared(): void
    {
        self::assertSame('shared', $this->connection()->fetchOne('SHOW search_path'));
        // An unqualified name resolves into `shared` (the Messenger transport relies on it) ...
        self::assertSame('messenger_messages', $this->connection()->fetchOne("SELECT to_regclass('messenger_messages')::text"));
        self::assertSame(0, $this->dbInt('SELECT count(*) FROM messenger_messages WHERE false'));
    }

    public function test_an_unqualified_module_table_does_not_resolve(): void
    {
        $this->expectFailure('SELECT 1 FROM user_account', 'does not exist');
    }

    public function test_the_application_has_usage_but_cannot_create_in_any_module_schema(): void
    {
        foreach (ModuleCatalog::SCHEMAS as $schema) {
            self::assertTrue($this->bool("SELECT has_schema_privilege('eventor_app', '$schema', 'USAGE')"), "USAGE on $schema");
            self::assertFalse($this->bool("SELECT has_schema_privilege('eventor_app', '$schema', 'CREATE')"), "no CREATE on $schema");
        }
        self::assertFalse($this->bool("SELECT has_schema_privilege('eventor_app', 'public', 'USAGE')"), 'public stays unused');

        $this->expectFailure('CREATE TABLE shared.hacked (id int)', 'permission denied');
        $this->expectFailure('CREATE TABLE public.hacked (id int)', 'permission denied');
    }

    public function test_module_tables_get_dml_through_default_privileges(): void
    {
        $owner = $this->owner();
        $owner->beginTransaction();
        try {
            $owner->executeStatement('CREATE TABLE catalog.grant_probe (id int)');
            $owner->executeStatement('CREATE TABLE shared.grant_probe (id serial PRIMARY KEY)');
            foreach (['catalog.grant_probe', 'shared.grant_probe'] as $table) {
                foreach (['SELECT', 'INSERT', 'UPDATE', 'DELETE'] as $privilege) {
                    self::assertTrue($this->bool("SELECT has_table_privilege('eventor_app', '$table', '$privilege')", $owner), "$privilege on $table");
                }
                self::assertFalse($this->bool("SELECT has_table_privilege('eventor_app', '$table', 'TRUNCATE')", $owner), "no TRUNCATE on $table");
            }
            self::assertTrue($this->bool("SELECT has_sequence_privilege('eventor_app', 'shared.grant_probe_id_seq', 'USAGE')", $owner), 'sequences are usable');
        } finally {
            $owner->rollBack();
        }
    }

    public function test_reporting_is_read_only_for_the_application(): void
    {
        $owner = $this->owner();
        $owner->beginTransaction();
        try {
            $owner->executeStatement('CREATE VIEW reporting.grant_probe AS SELECT 1 AS one');
            self::assertTrue($this->bool("SELECT has_table_privilege('eventor_app', 'reporting.grant_probe', 'SELECT')", $owner));
            foreach (['INSERT', 'UPDATE', 'DELETE', 'TRUNCATE'] as $privilege) {
                self::assertFalse($this->bool("SELECT has_table_privilege('eventor_app', 'reporting.grant_probe', '$privilege')", $owner), "no $privilege on reporting views");
            }
        } finally {
            $owner->rollBack();
        }
    }

    public function test_append_only_tables_accept_insert_and_select_but_never_update_delete_or_truncate(): void
    {
        $existing = 0;
        foreach (ModuleCatalog::APPEND_ONLY_TABLES as $table) {
            if (!$this->tableExists($table)) {
                continue; // owned by a module that is not built yet
            }
            ++$existing;

            self::assertTrue($this->bool("SELECT has_table_privilege('eventor_app', '$table', 'SELECT')"), "SELECT on $table");
            self::assertTrue($this->bool("SELECT has_table_privilege('eventor_app', '$table', 'INSERT')"), "INSERT on $table");
            foreach (['UPDATE', 'DELETE', 'TRUNCATE'] as $privilege) {
                self::assertFalse($this->bool("SELECT has_table_privilege('eventor_app', '$table', '$privilege')"), "$privilege on $table must be revoked");
            }
        }
        self::assertGreaterThanOrEqual(1, $existing, 'audit.audit_entry must exist and be append-only.');
    }

    public function test_an_update_or_delete_of_an_audit_entry_is_refused_by_the_database(): void
    {
        $this->createOwner('append@example.test', 'append-club'); // writes audit entries

        $this->expectFailure('UPDATE audit.audit_entry SET action = \'tampered\'', 'permission denied');
        $this->expectFailure('DELETE FROM audit.audit_entry', 'permission denied');
        $this->expectFailure('TRUNCATE audit.audit_entry', 'permission denied');
        self::assertSame(0, $this->dbInt("SELECT count(*) FROM audit.audit_entry WHERE action = 'tampered'"));
    }

    public function test_partially_updatable_tables_allow_only_their_listed_columns(): void
    {
        foreach (ModuleCatalog::PARTIALLY_UPDATABLE_TABLES as $table => $allowed) {
            if (!$this->tableExists($table)) {
                continue;
            }

            foreach (['DELETE', 'TRUNCATE'] as $privilege) {
                self::assertFalse($this->bool("SELECT has_table_privilege('eventor_app', '$table', '$privilege')"), "$privilege on $table");
            }
            self::assertFalse($this->bool("SELECT has_table_privilege('eventor_app', '$table', 'UPDATE')"), "table-wide UPDATE on $table");
            $columns = $this->connection()->fetchFirstColumn('SELECT column_name FROM information_schema.columns WHERE table_schema || \'.\' || table_name = ? ORDER BY ordinal_position', [$table]);
            foreach ($columns as $column) {
                \assert(\is_string($column));
                self::assertSame(
                    \in_array($column, $allowed, true),
                    $this->bool("SELECT has_column_privilege('eventor_app', '$table', '$column', 'UPDATE')"),
                    "column privilege UPDATE on $table.$column",
                );
            }
        }
        $this->addToAssertionCount(1);
    }

    public function test_the_migration_history_is_read_only_for_the_application(): void
    {
        self::assertTrue($this->bool("SELECT has_table_privilege('eventor_app', 'shared.doctrine_migration_versions', 'SELECT')"));
        foreach (['INSERT', 'UPDATE', 'DELETE'] as $privilege) {
            self::assertFalse($this->bool("SELECT has_table_privilege('eventor_app', 'shared.doctrine_migration_versions', '$privilege')"));
        }
    }

    private function owner(): Connection
    {
        $owner = static::getContainer()->get('doctrine.dbal.owner_connection');
        \assert($owner instanceof Connection);

        return $owner;
    }

    private function bool(string $sql, ?Connection $connection = null): bool
    {
        return (bool) ($connection ?? $this->connection())->fetchOne($sql);
    }

    private function tableExists(string $qualified): bool
    {
        return null !== $this->connection()->fetchOne('SELECT to_regclass(?)', [$qualified]);
    }

    /** Runs a statement that must fail with the given message, inside a savepoint so the test transaction survives. */
    private function expectFailure(string $sql, string $messagePart): void
    {
        $connection = $this->connection();
        $connection->createSavepoint('expect_failure');
        try {
            $connection->executeStatement($sql);
            self::fail("Expected the statement to fail ($messagePart): $sql");
        } catch (DbalException $e) {
            self::assertStringContainsString($messagePart, $e->getMessage(), $sql);
        } finally {
            $connection->rollbackSavepoint('expect_failure');
        }
    }
}
