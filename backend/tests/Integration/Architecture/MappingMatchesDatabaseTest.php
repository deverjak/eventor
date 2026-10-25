<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Architecture;

use Doctrine\ORM\EntityManagerInterface;
use Eventor\Tests\Support\IntegrationTestCase;

/**
 * `doctrine:schema:validate` cannot be used for the sync check (hand-written migrations add CHECK constraints, partial
 * indexes and foreign keys that the mapping does not know), so this test verifies what matters: every mapped
 * entity has its table and every mapped column in the database with matching nullability, and no NOT NULL column
 * without default is missing from the mapping (an INSERT would fail).
 */
final class MappingMatchesDatabaseTest extends IntegrationTestCase
{
    public function test_every_entity_matches_its_table(): void
    {
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        \assert($em instanceof EntityManagerInterface);

        $checked = 0;
        foreach ($em->getMetadataFactory()->getAllMetadata() as $metadata) {
            if (1 !== preg_match('/^Eventor\\\\(?<module>[A-Za-z0-9]+)\\\\/', $metadata->getName(), $m) || !isset(ModuleCatalog::SCHEMAS[$m['module']])) {
                continue; // fixtures use temporary tables
            }

            $schema = (string) $metadata->getSchemaName();
            $table = $metadata->getTableName();
            /** @var list<array{column_name: string, is_nullable: string, column_default: string|null, is_identity: string}> $columns */
            $columns = $this->connection()->fetchAllAssociative(
                'SELECT column_name, is_nullable, column_default, is_identity FROM information_schema.columns WHERE table_schema = ? AND table_name = ?',
                [$schema, $table],
            );
            self::assertNotEmpty($columns, "{$metadata->getName()}: table $schema.$table does not exist (missing migration?)");
            $byName = array_column($columns, null, 'column_name');

            $mapped = [];
            foreach ($metadata->fieldMappings as $field => $mapping) {
                $column = $mapping->columnName;
                $mapped[] = $column;
                self::assertArrayHasKey($column, $byName, "{$metadata->getName()}::\$$field maps to missing column $schema.$table.$column");
                self::assertSame(
                    $mapping->nullable ?? false,
                    'YES' === $byName[$column]['is_nullable'],
                    "{$metadata->getName()}::\$$field: nullability of $schema.$table.$column differs between mapping and database",
                );
            }

            foreach ($byName as $column => $info) {
                if (!\in_array($column, $mapped, true) && 'NO' === $info['is_nullable'] && null === $info['column_default'] && 'NO' === $info['is_identity']) {
                    self::fail("$schema.$table.$column is NOT NULL without default but {$metadata->getName()} does not map it.");
                }
            }
            ++$checked;
        }

        self::assertGreaterThanOrEqual(5, $checked);
    }

    public function test_doctrine_validates_the_mapping_itself(): void
    {
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        \assert($em instanceof EntityManagerInterface);

        $validator = new \Doctrine\ORM\Tools\SchemaValidator($em);
        $errors = array_filter($validator->validateMapping(), static fn(array $messages, string $class): bool => str_starts_with($class, 'Eventor\\') && !str_starts_with($class, 'Eventor\\Tests\\'), \ARRAY_FILTER_USE_BOTH);

        self::assertSame([], $errors);
    }
}
