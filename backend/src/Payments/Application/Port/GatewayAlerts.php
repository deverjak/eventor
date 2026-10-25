<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Port;

use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Operational signals of the Pairrot integration. Both end as `payments.integration_degraded`, which Attention turns
 * into an item for a human (a module may not call Attention directly; it publishes and Attention subscribes).
 */
interface GatewayAlerts
{
    /** The circuit breaker opened: Pairrot keeps failing (platform-wide, no organizer). */
    public function circuitOpened(\DateTimeImmutable $since, \DateTimeImmutable $reopensAt): void;

    /**
     * Something Eventor cannot reconcile with Pairrot: an unknown payment request, a request cancelled behind our
     * back, an open review case, a rejected message. `$reason` is a code with an optional `:subject` suffix, never free text.
     */
    public function anomaly(?OrganizerId $organizer, string $reason): void;
}
