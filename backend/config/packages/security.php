<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Eventor\Identity\Infrastructure\Security\ApiAuthenticationEntryPoint;
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
            ['path' => '^/api/(auth/(login|logout)|health|doc)', 'roles' => 'PUBLIC_ACCESS'],
            ['path' => '^/api/(admin|auth)', 'roles' => 'ROLE_USER'],
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
