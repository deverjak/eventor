<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Infrastructure\Messenger\ScheduleRetryStrategy;
use Eventor\Tests\Support\QueueDrainer;

// Test environment only. Test-support services, public aliases of private services, and the Doctrine
// transports used by the transactional-outbox proof (tests/Scenario/F0_TransactionalOutboxTest.php).
// The `<module>_events` transports are in-memory in the test environment (see ModuleServices::eventTransport).
return static function (ContainerConfigurator $container): void {
    $container->parameters()->set('eventor.integration_event_sources', []);

    $services = $container->services();
    $services->defaults()->autowire()->autoconfigure();

    // Fixture handlers/commands/subscribers (tests/Fixtures/{Messenger,Command}); the routing fixtures are plain classes.
    $services->load('Eventor\\Tests\\Fixtures\\Messenger\\', __DIR__.'/../tests/Fixtures/Messenger/');
    $services->load('Eventor\\Tests\\Fixtures\\Command\\', __DIR__.'/../tests/Fixtures/Command/');
    $services->load('Eventor\\Tests\\Fixtures\\Http\\', __DIR__.'/../tests/Fixtures/Http/');
    $services->get('Eventor\\Tests\\Fixtures\\Messenger\\ProbeRecorder')->public();

    $services->set(QueueDrainer::class)->public();
    $services->alias('test.command_bus', CommandBus::class)->public();

    // Public aliases (`test.<FQCN>`) so tests can fetch services that the container would otherwise inline or remove.
    // IntegrationTestCase::service(Foo::class) reads them.
    foreach ([
        \Eventor\Shared\Application\Tenant\TenantContext::class,
        \Eventor\Shared\Application\Audit\AuditContext::class,
        \Eventor\Shared\Application\Audit\AuditRecorder::class,
        \Eventor\Shared\Application\Idempotency\IdempotencyStore::class,
        \Eventor\Shared\Infrastructure\Doctrine\TenantFilterSynchronizer::class,
        \Eventor\Shared\Infrastructure\Doctrine\TenantFilterGuard::class,
        \Eventor\Audit\Contract\AuditTrail::class,
        \Eventor\Identity\Contract\AccessPolicy::class,
        \Eventor\Identity\Contract\OrganizerDirectory::class,
    ] as $class) {
        $services->alias('test.'.$class, $class)->public();
    }

    // Test-only entities (temporary tables created by the tests).
    $container->extension('doctrine', ['orm' => ['entity_managers' => ['default' => ['mappings' => [
        'TestFixtures' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => __DIR__.'/../tests/Fixtures/Doctrine',
            'prefix' => 'Eventor\\Tests\\Fixtures\\Doctrine',
            'alias' => 'TestFixtures',
        ],
    ]]]]]);

    // Real outbox: Doctrine transports on the ORM connection (rolled back with the test transaction by dama).
    // No LISTEN/NOTIFY waiting (get_notify_timeout 0): the in-process drain must return as soon as the queue is empty.
    $retry = ['service' => ScheduleRetryStrategy::class];
    $options = ['auto_setup' => false, 'get_notify_timeout' => 0, 'check_delayed_interval' => 0];
    $container->extension('framework', ['messenger' => ['transports' => [
        'probe_outbox' => ['dsn' => 'doctrine://default?queue_name=probe_outbox', 'options' => $options, 'retry_strategy' => $retry],
        'probe_flaky' => ['dsn' => 'doctrine://default?queue_name=probe_flaky', 'options' => $options, 'retry_strategy' => $retry],
        'probe_failing' => ['dsn' => 'doctrine://default?queue_name=probe_failing', 'options' => $options, 'retry_strategy' => $retry],
    ]]]);
};
