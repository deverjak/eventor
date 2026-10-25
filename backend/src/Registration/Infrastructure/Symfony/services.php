<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Eventor\Registration\Application\Capacity\AvailabilityInvalidator;
use Eventor\Registration\Application\Capacity\CapacityReleaser;
use Eventor\Registration\Application\Capacity\Query\CapacityConsistencyCheck;
use Eventor\Registration\Contract\AvailabilityReader;
use Eventor\Registration\Contract\CapacityProvisioner;
use Eventor\Registration\Contract\CapacityQuery;
use Eventor\Registration\Domain\Capacity\CapacityAllocator;
use Eventor\Shared\Infrastructure\Symfony\ModuleServices;

// Services and framework configuration of the Registration module. Add the module's own configuration below
// (Messenger transport, workflows, Doctrine DBAL types, ...) so that shared config files stay untouched.
return static function (ContainerConfigurator $container): void {
    $services = ModuleServices::register($container, 'Registration', \dirname(__DIR__, 2));
    ModuleServices::eventTransport($container, 'registration');

    // Public availability cache (CachedAvailabilityReader): tag-aware, shared between the php and worker containers
    // through Postgres (shared.cache_items), because the worker invalidates what the web process cached. Tests use
    // an in-process array pool.
    $container->extension('framework', ['cache' => ['pools' => ['cache.availability' => 'test' === $container->env()
        ? ['adapter' => 'cache.adapter.array', 'provider' => null, 'tags' => true, 'default_lifetime' => 60]
        : ['adapter' => 'cache.adapter.doctrine_dbal', 'provider' => 'doctrine.dbal.default_connection', 'tags' => true, 'default_lifetime' => 60],
    ]]]);

    if ('test' === $container->env()) {
        // Public aliases for the tests of the capacity core, and the console command the concurrency harness runs.
        foreach ([CapacityAllocator::class, CapacityProvisioner::class, CapacityQuery::class, AvailabilityReader::class, AvailabilityInvalidator::class, CapacityReleaser::class, CapacityConsistencyCheck::class] as $class) {
            $services->alias('test.'.$class, $class)->public();
        }
        $services->alias('test.cache.availability', 'cache.availability')->public();
        $services->set('Eventor\Tests\Support\Concurrency\ClaimSeatsCommand')->autowire()->autoconfigure();
    }
};
