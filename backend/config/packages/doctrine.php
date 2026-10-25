<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Eventor\Shared\Infrastructure\Doctrine\OrganizerFilter;

// Two connections to one database (ADR 013, chapter 03):
//   default  eventor_app    DML only; used by the application, workers and the Messenger transports
//   owner    eventor_owner  DDL; used only by doctrine:migrations (see doctrine_migrations.php)
// Module-specific mappings and DBAL types are added by each module's Infrastructure/Symfony/services.php.
return App::config([
    'doctrine' => [
        'dbal' => [
            'default_connection' => 'default',
            'connections' => [
                'default' => [
                    'url' => '%env(resolve:DATABASE_URL)%',
                    'profiling_collect_backtrace' => '%kernel.debug%',
                ],
                'owner' => [
                    'url' => '%env(resolve:DATABASE_MIGRATIONS_URL)%',
                ],
            ],
        ],
        'orm' => [
            'default_entity_manager' => 'default',
            'entity_managers' => [
                'default' => [
                    'connection' => 'default',
                    'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
                    'validate_xml_mapping' => true,
                    'auto_mapping' => false,
                    'filters' => [
                        'organizer' => [
                            'class' => OrganizerFilter::class,
                            'enabled' => true,
                        ],
                    ],
                ],
            ],
        ],
    ],
    'when@test' => [
        'doctrine' => [
            'dbal' => [
                'connections' => [
                    // TEST_TOKEN=identity -> eventor_test_identity (parallel agents never share a database)
                    'default' => ['dbname_suffix' => '_test_%env(TEST_TOKEN)%'],
                    'owner' => ['dbname_suffix' => '_test_%env(TEST_TOKEN)%'],
                ],
            ],
        ],
    ],
    'when@prod' => [
        'doctrine' => [
            'orm' => [
                'entity_managers' => [
                    'default' => [
                        'query_cache_driver' => ['type' => 'pool', 'pool' => 'doctrine.system_cache_pool'],
                        'result_cache_driver' => ['type' => 'pool', 'pool' => 'doctrine.result_cache_pool'],
                    ],
                ],
            ],
        ],
        'framework' => [
            'cache' => [
                'pools' => [
                    'doctrine.result_cache_pool' => ['adapter' => 'cache.app'],
                    'doctrine.system_cache_pool' => ['adapter' => 'cache.system'],
                ],
            ],
        ],
    ],
]);
