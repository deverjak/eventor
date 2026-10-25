<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Event;

use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Domain event: payment and cancellation rules of the event changed (EVT-10).
 */
final readonly class PaymentRulesChanged implements Auditable
{
    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    public function __construct(
        public OrganizerId $organizerId,
        public EventId $eventId,
        public array $before,
        public array $after,
        public bool $live,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange('event', $this->eventId->toString(), 'event.payment_rules_changed', $this->organizerId, $this->before, $this->after);
    }
}
