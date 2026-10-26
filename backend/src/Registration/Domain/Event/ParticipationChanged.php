<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Event;

use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * Domain event: one dimension of a participation changed (`state` by a workflow transition, `legal_state`,
 * `answers_state`, `hold_expires_at`). Audit only (chapter 05: every transition leaves an audit entry with actor and
 * reason); the integration events are published from the order-level facts below. Neutral values only.
 */
final readonly class ParticipationChanged implements Auditable
{
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public ParticipationId $participationId,
        public string $dimension,
        public string $from,
        public string $to,
        public string $transition,
        public ?string $reason = null,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange(
            'participation',
            $this->participationId->toString(),
            'participation.'.$this->dimension.'.'.$this->transition,
            $this->organizerId,
            [$this->dimension => $this->from],
            [$this->dimension => $this->to, 'orderId' => $this->orderId->toString()],
            $this->reason,
        );
    }
}
