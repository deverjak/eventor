<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'nelmio_api_doc' => [
        'documentation' => [
            'info' => [
                'title' => 'Eventor API',
                'description' => 'Command-oriented HTTP API of the Eventor event platform (ADR 008).',
                'version' => '0.1.0',
            ],
            'servers' => [['url' => '/']],
        ],
        'areas' => [
            'default' => ['path_patterns' => ['^/api(?!/doc)']],
        ],
    ],
]);
