<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

return App::config([
    'framework' => [
        'secret' => '%env(APP_SECRET)%',
        'default_locale' => 'cs',
        'http_method_override' => false,
        'handle_all_throwables' => true,
        'php_errors' => ['log' => true],
        'trusted_proxies' => '%env(default::TRUSTED_PROXIES)%',
        'trusted_headers' => ['x-forwarded-for', 'x-forwarded-host', 'x-forwarded-proto', 'x-forwarded-port'],
        'router' => ['default_uri' => '%env(DEFAULT_URI)%'],
        'property_info' => ['with_constructor_extractor' => true],
        'serializer' => ['enabled' => true],
        'validation' => ['email_validation_mode' => 'html5'],
        'lock' => '%env(LOCK_DSN)%',
        'mailer' => ['dsn' => '%env(MAILER_DSN)%'],
        'translator' => [
            'default_path' => '%kernel.project_dir%/translations',
            'fallbacks' => ['cs', 'en'],
        ],
        // ADR 006: the team session lives in Postgres (shared.sessions); cookie is HttpOnly + SameSite=Lax.
        'session' => [
            'handler_id' => PdoSessionHandler::class,
            'name' => 'EVENTOR_SESSID',
            'cookie_secure' => 'auto',
            'cookie_httponly' => true,
            'cookie_samesite' => 'lax',
            'gc_maxlifetime' => 28800, // 8 h of inactivity
        ],
        // ADR 006: stateless same-origin / double-submit CSRF protection for the cookie-authenticated API.
        // Clients send any random token (>= 24 chars) in the `csrf-token` header on unsafe requests
        // (see CsrfGuardListener and backend/README.md).
        'csrf_protection' => [
            'stateless_token_ids' => ['api'],
            'check_header' => 2, // SameOriginCsrfTokenManager::CHECK_ONLY_HEADER
            'cookie_name' => 'csrf-token',
        ],
        'rate_limiter' => [
            'public_order_create' => [
                'policy' => 'sliding_window',
                'limit' => 10,
                'interval' => '10 minutes',
                'cache_pool' => 'cache.rate_limiter',
            ],
        ],
        'cache' => [
            'pools' => [
                // Shared between the php and worker containers: Postgres-backed (table shared.cache_items).
                'cache.rate_limiter' => [
                    'adapter' => 'cache.adapter.doctrine_dbal',
                    'provider' => 'doctrine.dbal.default_connection',
                    'default_lifetime' => 3600,
                ],
            ],
        ],
    ],
    'when@test' => [
        'framework' => [
            'test' => true,
            'session' => [
                'handler_id' => null,
                'storage_factory_id' => 'session.storage.factory.mock_file',
            ],
            'validation' => ['not_compromised_password' => false],
            'cache' => [
                'pools' => [
                    'cache.rate_limiter' => ['adapter' => 'cache.adapter.array', 'provider' => null],
                ],
            ],
        ],
    ],
]);
