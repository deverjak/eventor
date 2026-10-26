<?php

declare(strict_types=1);

namespace Eventor\Communication\Domain\Event;

use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Domain event: the reminder schedule (PAY-09) of an organizer changed. Audited.
 */
final readonly class ReminderSettingsChanged implements Auditable
{
    /**
     * @param list<int> $paymentOffsetsHours
     */
    public function __construct(
        public OrganizerId $organizerId,
        public array $paymentOffsetsHours,
        public int $preEventHours,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange('reminder_settings', $this->organizerId->toString(), 'communication.reminder_settings_changed', $this->organizerId, after: [
            'paymentOffsetsHours' => $this->paymentOffsetsHours,
            'preEventHours' => $this->preEventHours,
        ]);
    }
}
