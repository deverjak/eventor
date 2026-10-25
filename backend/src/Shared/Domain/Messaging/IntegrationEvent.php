<?php

declare(strict_types=1);

namespace Eventor\Shared\Domain\Messaging;

/**
 * Marks a class in `<Module>\Contract\Event` as a public, versioned integration event (chapter 06, catalogue).
 * Routing to the subscribers' transports is derived from their handlers by IntegrationEventRoutingPass.
 * Integration events carry ids and neutral values only: no personal data.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class IntegrationEvent
{
    public function __construct(
        public string $name,
        public int $version = 1,
    ) {}
}
