<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

// Hand-written migrations (VersionYYYYMMDDHHMMSS), run as the schema owner. The version table lives in `shared`.
return App::config([
    'doctrine_migrations' => [
        'connection' => 'owner',
        'migrations_paths' => [
            'DoctrineMigrations' => '%kernel.project_dir%/migrations',
        ],
        // Unqualified on purpose: the owner role's search_path is `shared`, so the table lives there. A qualified name
        // ('shared.doctrine_migration_versions') is not recognised by the metadata storage's existence check on Postgres.
        'storage' => ['table_storage' => ['table_name' => 'doctrine_migration_versions']],
        'all_or_nothing' => true,
        'enable_profiler' => false,
    ],
]);
