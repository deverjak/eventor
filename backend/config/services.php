<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

// Application-wide parameters only. Services are declared per module in
// src/<Module>/Infrastructure/Symfony/services.php (imported by the Kernel); shared kernel services live in
// src/Shared/Infrastructure/Symfony/services.php.
return App::config([
    'parameters' => [
        'eventor.public_url' => '%env(EVENTOR_PUBLIC_URL)%',
        // ADR 001: every command records a domain event (or is declared #[ReadOnlyCommand] / #[NoDomainEvent]).
        // Enforced by DomainEventsMiddleware outside production.
        'eventor.require_domain_event' => true,
    ],
    'when@prod' => [
        'parameters' => ['eventor.require_domain_event' => false],
    ],
]);
