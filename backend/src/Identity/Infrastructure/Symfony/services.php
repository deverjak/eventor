<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Eventor\Identity\Infrastructure\Doctrine\MembershipIdType;
use Eventor\Identity\Infrastructure\Doctrine\UserAccountIdType;
use Eventor\Shared\Infrastructure\Symfony\ModuleServices;
use Symfony\Component\Security\Http\RateLimiter\DefaultLoginRateLimiter;

// Everything module-specific is declared here, so parallel work on other modules never touches config/packages:
// services + Doctrine mapping (ModuleServices::register), DBAL types, rate limiters and the inbound event transport.
return static function (ContainerConfigurator $container): void {
    $services = ModuleServices::register($container, 'Identity', \dirname(__DIR__, 2));

    ModuleServices::dbalTypes($container, [
        UserAccountIdType::NAME => UserAccountIdType::class,
        MembershipIdType::NAME => MembershipIdType::class,
    ]);

    // `identity_events`: outbox queue of integration events that Identity subscribes to.
    ModuleServices::eventTransport($container, 'identity');

    // Login throttling (chapter 08): LOGIN_MAX_ATTEMPTS per minute and username+IP, LOGIN_GLOBAL_MAX_ATTEMPTS per hour
    // and IP. The black-box API stack raises both through its environment.
    $container->extension('framework', ['rate_limiter' => [
        'login_local' => [
            'policy' => 'sliding_window',
            'limit' => '%env(int:LOGIN_MAX_ATTEMPTS)%',
            'interval' => '1 minute',
            'cache_pool' => 'cache.rate_limiter',
        ],
        'login_global' => [
            'policy' => 'sliding_window',
            'limit' => '%env(int:LOGIN_GLOBAL_MAX_ATTEMPTS)%',
            'interval' => '1 hour',
            'cache_pool' => 'cache.rate_limiter',
        ],
    ]]);

    $services->set('identity.login_rate_limiter', DefaultLoginRateLimiter::class)
        ->args([service('limiter.login_global'), service('limiter.login_local'), '%kernel.secret%']);
};
