<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Eventor\Shared\Infrastructure\Messenger\AuditContextMiddleware;
use Eventor\Shared\Infrastructure\Messenger\DomainEventsMiddleware;
use Eventor\Shared\Infrastructure\Messenger\IdempotencyMiddleware;
use Eventor\Shared\Infrastructure\Messenger\TenantContextMiddleware;

// Shared Messenger kernel (chapter 09, ADR 003). Per-module `<module>_events` transports are declared by
// the owning module in src/<Module>/Infrastructure/Symfony/services.php (ModuleServices::eventTransport()),
// and IntegrationEventRoutingPass derives the event -> transport routing from the subscribers.
return App::config([
    'framework' => [
        'messenger' => [
            'default_bus' => 'command.bus',
            'failure_transport' => 'failed',
            'serializer' => [
                'default_serializer' => 'messenger.transport.symfony_serializer',
                'symfony_serializer' => ['format' => 'json'],
            ],
            'buses' => [
                // One command = one transaction (stateful writes, audit and outbox rows commit together).
                'command.bus' => [
                    'middleware' => [
                        'validation',
                        TenantContextMiddleware::class,
                        IdempotencyMiddleware::class,
                        'doctrine_transaction',
                        AuditContextMiddleware::class,
                        DomainEventsMiddleware::class,
                    ],
                ],
                // Domain events (sync, in the command transaction) and integration events (routed to transports).
                'event.bus' => [
                    'default_middleware' => ['enabled' => true, 'allow_no_handlers' => true],
                    'middleware' => [
                        TenantContextMiddleware::class,
                        AuditContextMiddleware::class,
                        'doctrine_ping_connection',
                    ],
                ],
            ],
            'transports' => [
                'failed' => [
                    'dsn' => 'doctrine://default?queue_name=failed',
                    'options' => ['auto_setup' => false],
                ],
                'async' => [
                    'dsn' => 'doctrine://default?queue_name=async',
                    'options' => ['redeliver_timeout' => 300, 'auto_setup' => false],
                ],
            ],
        ],
    ],
    'when@test' => [
        'framework' => [
            'messenger' => [
                'transports' => [
                    'failed' => ['dsn' => 'in-memory://'],
                    'async' => ['dsn' => 'in-memory://'],
                ],
            ],
        ],
    ],
]);
