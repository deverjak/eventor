<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Architecture;

use Doctrine\ORM\EntityManagerInterface;
use Eventor\Tests\Support\IntegrationTestCase;

/**
 * ADR 013: the schema is the owner. Every entity of module X lives in `Eventor\X\Domain\` and maps to schema `x`;
 * no module maps a table into another module's schema.
 */
final class TableOwnershipTest extends IntegrationTestCase
{
    public function test_every_entity_maps_to_the_schema_of_its_module(): void
    {
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        \assert($em instanceof EntityManagerInterface);

        $checked = [];
        foreach ($em->getMetadataFactory()->getAllMetadata() as $metadata) {
            if (1 !== preg_match('/^Eventor\\\\(?<module>[A-Za-z0-9]+)\\\\(?<rest>.+)$/', $metadata->getName(), $m) || !isset(ModuleCatalog::SCHEMAS[$m['module']])) {
                continue; // test fixtures
            }

            self::assertStringStartsWith('Domain\\', $m['rest'], \sprintf('Entity %s must live in Eventor\%s\Domain.', $metadata->getName(), $m['module']));
            self::assertSame(
                ModuleCatalog::SCHEMAS[$m['module']],
                $metadata->getSchemaName(),
                \sprintf('Entity %s must map to schema "%s" (#[ORM\Table(schema: …)]).', $metadata->getName(), ModuleCatalog::SCHEMAS[$m['module']]),
            );
            $checked[] = $metadata->getName();
        }

        self::assertGreaterThanOrEqual(5, \count($checked), 'The test must actually inspect the module entities.');
    }

    public function test_every_module_table_in_the_database_lives_in_a_module_schema(): void
    {
        $leaked = $this->connection()->fetchFirstColumn(
            "SELECT table_schema || '.' || table_name FROM information_schema.tables
              WHERE table_schema NOT IN ('pg_catalog', 'information_schema', 'pg_toast') AND table_schema NOT LIKE 'pg_temp%' AND table_schema NOT IN ('".implode("','", ModuleCatalog::SCHEMAS)."')",
        );

        self::assertSame([], $leaked, 'Tables outside the module schemas and `shared` (public must stay empty).');
    }
}
