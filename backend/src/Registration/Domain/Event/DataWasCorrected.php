<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Event;

use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Domain event: the data of a person or of the order contact was corrected (REG-09). Audit only. Personal data never
 * enters the audit (chapter 08): the entry names the form fields that changed, not their values.
 */
final readonly class DataWasCorrected implements Auditable
{
    /**
     * @param list<string> $fields form field keys whose answers changed
     */
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public string $subjectType,
        public string $subjectId,
        public array $fields,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange(
            $this->subjectType,
            $this->subjectId,
            $this->subjectType.'.data_corrected',
            $this->organizerId,
            null,
            ['orderId' => $this->orderId->toString(), 'fields' => $this->fields],
        );
    }
}
