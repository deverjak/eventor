<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Eventor\Identity\Infrastructure\Security\ApiAuthenticationEntryPoint;
use Eventor\Identity\Infrastructure\Security\DeviceAuthenticationFailureHandler;
use Eventor\Identity\Infrastructure\Security\DeviceRequestMatcher;
use Eventor\Identity\Infrastructure\Security\DeviceTokenHandler;
use Eventor\Identity\Infrastructure\Security\LoginFailureHandler;
use Eventor\Identity\Infrastructure\Security\LoginSuccessHandler;
use Eventor\Identity\Infrastructure\Security\SecurityUser;
use Eventor\Identity\Infrastructure\Security\SecurityUserProvider;

// ADR 006. W0 ships the team firewall only; the webhook, self_service and check_in firewalls are added by the
// modules that own those authenticators (they must be declared BEFORE `team`: first match wins).
// This file is a shared file: only the coordinator or an agent explicitly assigned to it edits it.
return App::config([
    'security' => [
        'password_hashers' => [
            SecurityUser::class => 'auto', // Argon2id
        ],
        'providers' => [
            'identity_users' => ['id' => SecurityUserProvider::class],
        ],
        'firewalls' => [
            // Pairrot webhooks (API-02, Payments): stateless, authenticated by the HMAC signature of the raw body.
            'webhooks' => [
                'pattern' => '^/api/webhooks',
                'stateless' => true,
                'provider' => 'identity_users',
                'custom_authenticators' => [\Eventor\Payments\UserInterface\Security\PairrotSignatureAuthenticator::class],
            ],
            // Participant self-service (API-04, Registration): `Authorization: SelfService ss1_<id>_<secret>`, stateless.
            'self_service' => [
                'pattern' => '^/api/self-service',
                'stateless' => true,
                'provider' => 'identity_users',
                'entry_point' => \Eventor\Registration\UserInterface\Security\SelfServiceTokenAuthenticator::class,
                'custom_authenticators' => [\Eventor\Registration\UserInterface\Security\SelfServiceTokenAuthenticator::class],
            ],
            // Check-in devices (ADR 006): `Authorization: Bearer dev1_...` on /api/check-in, stateless. A request to the
            // same path without a device token (a signed-in team member) falls through to `team` and its session.
            'check_in' => [
                'request_matcher' => DeviceRequestMatcher::class,
                'stateless' => true,
                'access_token' => [
                    'token_handler' => DeviceTokenHandler::class,
                    'failure_handler' => DeviceAuthenticationFailureHandler::class,
                ],
            ],
            'team' => [
                'pattern' => '^/api',
                'lazy' => true,
                'provider' => 'identity_users',
                'entry_point' => ApiAuthenticationEntryPoint::class,
                'json_login' => [
                    'check_path' => '/api/auth/login',
                    'username_path' => 'email',
                    'password_path' => 'password',
                    'success_handler' => LoginSuccessHandler::class,
                    'failure_handler' => LoginFailureHandler::class,
                ],
                'logout' => ['path' => '/api/auth/logout'],
                // 5 attempts per minute per username+IP and 50 per hour per IP (limits from env, see Identity services.php).
                'login_throttling' => ['limiter' => 'identity.login_rate_limiter'],
            ],
        ],
        'access_control' => [
            ['path' => '^/api/(auth/(login|logout|sign-up|invitations/(preview|accept))|health|doc)', 'roles' => 'PUBLIC_ACCESS'],
            ['path' => '^/api/(admin|auth)', 'roles' => 'ROLE_USER'],
            ['path' => '^/api/self-service', 'roles' => 'ROLE_PARTICIPANT'],
            ['path' => '^/api/check-in', 'roles' => ['ROLE_USER', 'ROLE_DEVICE']],
        ],
    ],
    'when@test' => [
        'security' => [
            'password_hashers' => [
                SecurityUser::class => [
                    'algorithm' => 'auto',
                    'cost' => 4, // lowest bcrypt cost
                    'time_cost' => 3, // lowest argon values
                    'memory_cost' => 10,
                ],
            ],
        ],
    ],
]);
