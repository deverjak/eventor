<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Architecture;

/**
 * The twelve modules plus the shared kernel: namespace segment => Postgres schema. Architecture tests iterate
 * this, so a new module is covered the moment it is listed here (and in Kernel::modules()).
 */
final class ModuleCatalog
{
    /** @var array<string, string> */
    public const array SCHEMAS = [
        'Identity' => 'identity',
        'Catalog' => 'catalog',
        'Legal' => 'legal',
        'Registration' => 'registration',
        'Payments' => 'payments',
        'CheckIn' => 'checkin',
        'Communication' => 'communication',
        'Content' => 'content',
        'Privacy' => 'privacy',
        'Audit' => 'audit',
        'Attention' => 'attention',
        'Reporting' => 'reporting',
        'Shared' => 'shared',
    ];

    /**
     * Tables the application role may only INSERT and SELECT (chapter 03, "Append-only a granty").
     * A module that adds an append-only table adds it here AND revokes UPDATE/DELETE/TRUNCATE in its migration:
     * SchemaGrantsTest and MigrationsGrantTest verify both.
     *
     * @var list<string>
     */
    public const array APPEND_ONLY_TABLES = [
        'audit.audit_entry',
        'payments.ledger_entry',
        'legal.confirmation',
    ];

    /**
     * Tables where the application role may update only the listed columns (everything else is append-only).
     *
     * @var array<string, list<string>>
     */
    public const array PARTIALLY_UPDATABLE_TABLES = [
        'checkin.check_in' => ['voided_at', 'voided_by', 'void_reason'],
    ];

    public static function projectDir(): string
    {
        return \dirname(__DIR__, 3);
    }

    /**
     * Every class in a module's `Contract/Event` directory: module => list of event classes.
     *
     * @return list<array{module: string, class: class-string}>
     */
    public static function contractEvents(): array
    {
        $events = [];
        $files = glob(self::projectDir().'/src/*/Contract/Event/*.php');
        foreach (false === $files ? [] : $files as $file) {
            $parts = explode('/', str_replace('\\', '/', $file));
            $module = $parts[\count($parts) - 4];
            $class = \sprintf('Eventor\\%s\\Contract\\Event\\%s', $module, basename($file, '.php'));
            if (class_exists($class)) {
                $events[] = ['module' => $module, 'class' => $class];
            }
        }

        return $events;
    }
}
