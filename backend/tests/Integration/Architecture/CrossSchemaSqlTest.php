<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Architecture;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * ADR 013: raw SQL is always schema-qualified and a module's SQL names only its own schema (and `shared`).
 * Cross-module reads go through `Contract/` queries, never through a join into a foreign schema. Reporting reads its
 * own views in `reporting` (they are created in migrations), so it needs no exception either.
 */
final class CrossSchemaSqlTest extends TestCase
{
    public function test_no_module_names_a_foreign_schema_in_its_sql(): void
    {
        $schemas = array_values(ModuleCatalog::SCHEMAS);
        $pattern = '/\b(?:FROM|JOIN|INTO|UPDATE|TABLE|TRUNCATE)\s+(?<schema>'.implode('|', $schemas).')\.[a-z_]+/i';

        $violations = [];
        $scanned = 0;
        foreach (ModuleCatalog::SCHEMAS as $module => $schema) {
            $directory = ModuleCatalog::projectDir().'/src/'.$module;
            if (!is_dir($directory)) {
                continue;
            }

            foreach ((new Finder())->files()->in($directory)->name('*.php') as $file) {
                ++$scanned;
                preg_match_all($pattern, $file->getContents(), $matches, \PREG_SET_ORDER);
                foreach ($matches as $match) {
                    $used = strtolower($match['schema']);
                    if ($used !== $schema && 'shared' !== $used) {
                        $violations[] = \sprintf('%s names schema "%s": %s', $file->getRelativePathname(), $used, trim($match[0]));
                    }
                }
            }
        }

        self::assertGreaterThan(10, $scanned);
        self::assertSame([], $violations, "A module's SQL must name only its own schema:\n".implode("\n", $violations));
    }

    public function test_sql_in_modules_never_uses_an_unqualified_table_of_a_module(): void
    {
        // The application role has search_path = shared, so an unqualified module table does not even resolve.
        $tables = ['user_account', 'organizer', 'membership', 'audit_entry', 'attention_item'];
        $pattern = '/\b(?:FROM|JOIN|INTO|UPDATE)\s+(?:'.implode('|', $tables).')\b/i';

        $violations = [];
        foreach ((new Finder())->files()->in(ModuleCatalog::projectDir().'/src')->name('*.php') as $file) {
            if (1 === preg_match($pattern, $file->getContents(), $match)) {
                $violations[] = $file->getRelativePathname().': '.$match[0];
            }
        }

        self::assertSame([], $violations, "Qualify module tables with their schema:\n".implode("\n", $violations));
    }
}
