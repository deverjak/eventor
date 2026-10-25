<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

// Every integration test runs in a transaction that is rolled back (static connection shared by the kernel
// and the test), so tests stay isolated and fast. Concurrency tests opt out and clean up with TRUNCATE.
return App::config([
    'when@test' => [
        'dama_doctrine_test' => [
            'enable_static_connection' => true,
            'enable_static_meta_data_cache' => true,
            'enable_static_query_cache' => true,
        ],
    ],
]);
