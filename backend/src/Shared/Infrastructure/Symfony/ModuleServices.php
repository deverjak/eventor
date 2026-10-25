<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Symfony;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;

/**
 * Helpers for a module's `Infrastructure/Symfony/services.php`, so every module wires itself the same way and
 * module-specific framework configuration never touches the shared files in config/packages.
 */
final class ModuleServices
{
    /**
     * Registers every class of the module as an autowired, autoconfigured service (except the domain model and the
     * public contract, which are not services) and adds the module's Doctrine attribute mapping.
     *
     * Implementations of `Contract` interfaces declare their alias on the class: `#[AsAlias(OrganizerDirectory::class)]`.
     */
    public static function register(ContainerConfigurator $container, string $module, string $moduleDir): ServicesConfigurator
    {
        $services = $container->services();
        $services->defaults()->autowire()->autoconfigure();

        $services->load(\sprintf('Eventor\\%s\\', $module), $moduleDir.'/')
            ->exclude([
                $moduleDir.'/Domain',
                $moduleDir.'/Contract',
                $moduleDir.'/Infrastructure/Symfony/services.php',
                $moduleDir.'/Infrastructure/Symfony/*Module.php',
            ]);

        if (is_dir($moduleDir.'/Domain')) {
            $container->extension('doctrine', [
                'orm' => ['entity_managers' => ['default' => ['mappings' => [
                    $module => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => $moduleDir.'/Domain',
                        'prefix' => \sprintf('Eventor\\%s\\Domain', $module),
                        'alias' => $module,
                    ],
                ]]]],
            ]);
        }

        return $services;
    }

    /**
     * Declares the module's inbound event transport `<module>_events`: a Doctrine transport on the ORM connection,
     * which makes it the transactional outbox (ADR 003), with the shared retry schedule. Handlers subscribe with
     * `#[AsMessageHandler(fromTransport: '<module>_events')]`; IntegrationEventRoutingPass derives the routing.
     * Tests use `in-memory://` and drain it with QueueDrainer.
     */
    public static function eventTransport(ContainerConfigurator $container, string $module): void
    {
        $name = $module.'_events';

        $container->extension('framework', ['messenger' => ['transports' => [
            $name => 'test' === $container->env()
                ? [
                    'dsn' => 'in-memory://?serialize=true',
                    'retry_strategy' => ['service' => 'Eventor\Shared\Infrastructure\Messenger\ScheduleRetryStrategy'],
                ]
                : [
                    'dsn' => 'doctrine://default?queue_name='.$name,
                    // Postgres NOTIFY is issued by the transport itself on send (inside the transaction), so the
                    // worker wakes up right after commit; auto_setup is off because the migration owns the table.
                    'options' => ['redeliver_timeout' => 300, 'auto_setup' => false],
                    'retry_strategy' => ['service' => 'Eventor\Shared\Infrastructure\Messenger\ScheduleRetryStrategy'],
                ],
        ]]]);
    }

    /**
     * Registers custom DBAL types (typed ids, ...) by name, e.g. `['order_id' => OrderIdType::class]`.
     *
     * @param array<string, class-string> $types
     */
    public static function dbalTypes(ContainerConfigurator $container, array $types): void
    {
        $container->extension('doctrine', ['dbal' => ['types' => $types]]);
    }
}
