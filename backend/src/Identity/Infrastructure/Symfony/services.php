<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Eventor\Identity\Application\ConnectPairrot\ConnectOrganizerToPairrotHandler;
use Eventor\Identity\Contract\InvitationQuery;
use Eventor\Identity\Contract\OrganizerLookup;
use Eventor\Identity\Contract\OrganizerSettings;
use Eventor\Identity\Infrastructure\Doctrine\DeviceTokenIdType;
use Eventor\Shared\Infrastructure\Symfony\ModuleServices;
use Eventor\Tests\Integration\Identity\Support\FakePairrotOnboarding;
use Eventor\Tests\Integration\Identity\Support\RecordingEventBus;
use Symfony\Component\Security\Http\RateLimiter\DefaultLoginRateLimiter;

// Everything module-specific is declared here, so parallel work on other modules never touches config/packages:
// services + Doctrine mapping (ModuleServices::register), rate limiters and the inbound event transport.
// (Typed ids that cross module boundaries, e.g. MembershipId, live in Shared and are registered by SharedIdTypes.)
return static function (ContainerConfigurator $container): void {
    $services = ModuleServices::register($container, 'Identity', \dirname(__DIR__, 2));

    // Module-internal typed id (the id of a device token is also the public half of the token).
    ModuleServices::dbalTypes($container, [DeviceTokenIdType::NAME => DeviceTokenIdType::class]);

    // `identity_events`: outbox queue of integration events that Identity subscribes to.
    ModuleServices::eventTransport($container, 'identity');

    // Defaults of the throttling limits when no environment variable sets them.
    $container->parameters()
        ->set('eventor.identity.signup_limit', \in_array($container->env(), ['dev', 'test'], true) ? 10000 : 20)
        // Whether `POST .../invitations` hands the accept token back to the inviter. Only development and test stacks
        // (which send no mail) do; in production the token travels in the invitation message only.
        ->set('eventor.identity.expose_invitation_token', \in_array($container->env(), ['dev', 'test'], true));

    // Login throttling (chapter 08): LOGIN_MAX_ATTEMPTS per minute and username+IP, LOGIN_GLOBAL_MAX_ATTEMPTS per hour
    // and IP. The black-box API stack raises both through its environment. Sign-up, invitation preview and
    // invitation acceptance share one per-address limit (SIGNUP_MAX_PER_HOUR, default 20).
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
        'identity_signup' => [
            'policy' => 'sliding_window',
            'limit' => '%env(int:default:eventor.identity.signup_limit:SIGNUP_MAX_PER_HOUR)%',
            'interval' => '1 hour',
            'cache_pool' => 'cache.rate_limiter',
        ],
    ]]);

    $services->set('identity.login_rate_limiter', DefaultLoginRateLimiter::class)
        ->args([service('limiter.login_global'), service('limiter.login_local'), '%kernel.secret%']);

    // Test double of Payments' PairrotOnboarding facade (the real adapter is Payments' job), test environment only.
    // Wired explicitly into the handler so that it wins whatever Payments registers under the interface.
    if ('test' === $container->env()) {
        // Public `test.<FQCN>` aliases of the facades the tests call directly (IntegrationTestCase::service()).
        foreach ([OrganizerSettings::class, OrganizerLookup::class, InvitationQuery::class] as $facade) {
            $services->alias('test.'.$facade, $facade)->public();
        }
        // Records the integration events dispatched on `event.bus` (pending subscribers have no transport to inspect).
        $services->set(RecordingEventBus::class)->decorate('event.bus')->args([service('.inner')]);
        $services->set(FakePairrotOnboarding::class)->public();
        $services->set(ConnectOrganizerToPairrotHandler::class)
            ->arg('$onboarding', service(FakePairrotOnboarding::class));
    }
};
