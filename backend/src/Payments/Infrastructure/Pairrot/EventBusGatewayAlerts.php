<?php

declare(strict_types=1);

namespace Eventor\Payments\Infrastructure\Pairrot;

use Eventor\Payments\Application\Port\GatewayAlerts;
use Eventor\Payments\Contract\Event\IntegrationDegraded;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Eventor\Shared\Domain\Id\OrganizerId;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Publishes `payments.integration_degraded`. An open circuit breaker is platform-wide (no organizer); an anomaly
 * belongs to the organizer whose message caused it, and is published in the transaction that noticed it.
 */
#[AsAlias(GatewayAlerts::class)]
final readonly class EventBusGatewayAlerts implements GatewayAlerts
{
    public function __construct(
        #[Target('event.bus')]
        private MessageBusInterface $eventBus,
        private ClockInterface $clock,
    ) {}

    public function circuitOpened(\DateTimeImmutable $since, \DateTimeImmutable $reopensAt): void
    {
        $this->eventBus->dispatch(new IntegrationDegraded(null, 'pairrot', 'circuit_open', $since, $since));
    }

    public function anomaly(?OrganizerId $organizer, string $reason): void
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $this->eventBus->dispatch(
            new IntegrationDegraded($organizer, 'pairrot', $reason, $now, $now),
            null === $organizer ? [] : [TenantStamp::of($organizer)],
        );
    }
}
