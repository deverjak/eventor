<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Domain\Event;

use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\CheckInId;
use Eventor\Shared\Domain\Id\CheckInListId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * Domain event: a check-in was voided (CHK-07), with the reason; audited. Published as `checkin.check_in_voided`.
 */
final readonly class CheckInWasVoided implements Auditable
{
    public function __construct(
        public OrganizerId $organizerId,
        public CheckInId $checkInId,
        public ParticipationId $participationId,
        public CheckInListId $listId,
        public string $voidedBy,
        public string $reason,
        public ?CheckInId $replacedBy,
        public \DateTimeImmutable $occurredAt,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange(
            entityType: 'check_in',
            entityId: $this->checkInId->toString(),
            action: null === $this->replacedBy ? 'check_in.voided' : 'check_in.corrected',
            organizerId: $this->organizerId,
            before: ['valid' => true],
            after: ['valid' => false, 'participationId' => $this->participationId->toString(), 'replacedBy' => $this->replacedBy?->toString()],
            reason: $this->reason,
        );
    }
}
