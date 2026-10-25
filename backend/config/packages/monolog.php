<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'monolog' => [
        'channels' => ['deprecation', 'outbox', 'audit_ops', 'integration'],
    ],
    'when@dev' => [
        'monolog' => [
            'handlers' => [
                'main' => [
                    'type' => 'stream',
                    'path' => 'php://stderr',
                    'level' => 'notice',
                    'formatter' => 'monolog.formatter.json',
                    'channels' => ['!event', '!doctrine'],
                ],
            ],
        ],
    ],
    'when@test' => [
        'monolog' => [
            'handlers' => [
                'main' => [
                    'type' => 'fingers_crossed',
                    'action_level' => 'error',
                    'handler' => 'nested',
                    'excluded_http_codes' => [404, 405],
                    'channels' => ['!event'],
                ],
                'nested' => [
                    'type' => 'stream',
                    'path' => '%kernel.logs_dir%/%kernel.environment%.log',
                    'level' => 'debug',
                ],
            ],
        ],
    ],
    'when@prod' => [
        'monolog' => [
            'handlers' => [
                'main' => [
                    'type' => 'fingers_crossed',
                    'action_level' => 'error',
                    'handler' => 'nested',
                    'excluded_http_codes' => [404, 405],
                    'channels' => ['!deprecation'],
                    'buffer_size' => 50,
                ],
                'nested' => [
                    'type' => 'stream',
                    'path' => 'php://stderr',
                    'level' => 'debug',
                    'formatter' => 'monolog.formatter.json',
                ],
                'deprecation' => [
                    'type' => 'stream',
                    'channels' => ['deprecation'],
                    'path' => 'php://stderr',
                    'formatter' => 'monolog.formatter.json',
                ],
            ],
        ],
    ],
]);
