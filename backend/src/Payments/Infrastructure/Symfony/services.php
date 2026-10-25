<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Eventor\Payments\Application\Port\PaymentGateway;
use Eventor\Payments\Application\Webhook\ProcessInboxMessage;
use Eventor\Payments\Domain\Refund\RefundTask;
use Eventor\Payments\Infrastructure\Pairrot\InMemoryPaymentGateway;
use Eventor\Payments\Infrastructure\Pairrot\Webhook\LocalWebhookSecret;
use Eventor\Shared\Infrastructure\Symfony\ModuleServices;

// Services and framework configuration of the Payments module (schema `payments`).
return static function (ContainerConfigurator $container): void {
    $services = ModuleServices::register($container, 'Payments', \dirname(__DIR__, 2));

    // `payments_events`: outbox queue of the integration events Payments subscribes to.
    ModuleServices::eventTransport($container, 'payments');

    // Environment defaults (real values: backend/.env.local written by bin/pairrot-bootstrap, or the vault in production).
    // The webhook secret default exists OUTSIDE production only, so tests and the black-box suite can sign webhooks;
    // in production an unset variable means "no secret", and every webhook is refused.
    $parameters = $container->parameters();
    $parameters->set('env(PAIRROT_BASE_URL)', 'http://pairrot:8080');
    $parameters->set('env(PAIRROT_MANAGEMENT_KEY)', '');
    $parameters->set('env(PAIRROT_WEBHOOK_SECRETS)', 'prod' === $container->env() ? '' : LocalWebhookSecret::KEY_ID.':'.LocalWebhookSecret::SECRET);
    $parameters->set('env(PAIRROT_SUBTENANT_PREFIX)', 'prod' === $container->env() ? '' : 'test_');

    if ('test' === $container->env()) {
        // ADR 004 / chapter 09: in tests the port is the in-memory fake and the decorator chain stays in front of it.
        $services->set(InMemoryPaymentGateway::class)->public();
        $services->alias(PaymentGateway::class, InMemoryPaymentGateway::class);
        // The event bus, so tests publish integration events the way a publisher does.
        $services->alias('test.event_bus', 'event.bus')->public();

        // Public aliases (`test.<FQCN>`, read by IntegrationTestCase::service()) of the services the tests call directly.
        foreach ([
            \Eventor\Payments\Contract\OrderFinanceInitializer::class,
            \Eventor\Payments\Contract\CashDesk::class,
            \Eventor\Payments\Contract\FinanceQuery::class,
            \Eventor\Payments\Contract\PairrotOnboarding::class,
            PaymentGateway::class,
        ] as $class) {
            $services->alias('test.'.$class, $class)->public();
        }
    }

    // `pairrot_inbox`: the queue of verified Pairrot messages (webhook and reconcile). Same shape as the `<module>_events`
    // outbox queues: Doctrine on the ORM connection in production (the insert commits with the inbox row), in memory in tests.
    $container->extension('framework', ['messenger' => [
        'transports' => ['pairrot_inbox' => 'test' === $container->env()
            ? ['dsn' => 'in-memory://?serialize=true', 'retry_strategy' => ['service' => 'Eventor\Shared\Infrastructure\Messenger\ScheduleRetryStrategy']]
            : [
                'dsn' => 'doctrine://default?queue_name=pairrot_inbox',
                'options' => ['redeliver_timeout' => 300, 'auto_setup' => false],
                'retry_strategy' => ['service' => 'Eventor\Shared\Infrastructure\Messenger\ScheduleRetryStrategy'],
            ],
        ],
        'routing' => [ProcessInboxMessage::class => 'pairrot_inbox'],
    ]]);

    $container->extension('framework', [
        // Problem titles of this module (domain `problems`, merged with the shared catalogue).
        'translator' => ['paths' => [\dirname(__DIR__, 2).'/Resources/translations']],
        'workflows' => [
            // ADR 011, chapter 05: the refund task is OPEN until finance settles it or someone cancels it.
            'refund_task' => [
                'type' => 'state_machine',
                'marking_store' => ['type' => 'method', 'property' => 'status'],
                'supports' => [RefundTask::class],
                'initial_marking' => RefundTask::OPEN,
                'places' => [RefundTask::OPEN, RefundTask::SETTLED, RefundTask::CANCELLED],
                'transitions' => [
                    'settle' => ['from' => RefundTask::OPEN, 'to' => RefundTask::SETTLED],
                    'cancel' => ['from' => RefundTask::OPEN, 'to' => RefundTask::CANCELLED],
                ],
            ],
        ],
        // The scoped client of the Pairrot adapter: base URL, management key, 10 s overall timeout (chapter 07).
        'http_client' => ['scoped_clients' => ['pairrot.client' => [
            'base_uri' => '%env(PAIRROT_BASE_URL)%',
            'headers' => ['Authorization' => 'Bearer %env(PAIRROT_MANAGEMENT_KEY)%', 'Accept' => 'application/json'],
            'timeout' => 10,
            'max_duration' => 10,
        ]]],
        // State of the circuit breaker: shared by the web and the worker container (Postgres), in-process in tests.
        'cache' => ['pools' => ['payments.circuit_breaker' => 'test' === $container->env()
            ? ['adapter' => 'cache.adapter.array', 'default_lifetime' => 3600]
            : ['adapter' => 'cache.adapter.doctrine_dbal', 'provider' => 'doctrine.dbal.default_connection', 'default_lifetime' => 3600],
        ]],
    ]);
};
