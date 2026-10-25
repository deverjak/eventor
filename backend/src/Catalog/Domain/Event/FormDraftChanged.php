<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Event;

use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\FormVersionId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Domain event: the draft of the registration form was created or edited (FRM-08). Internal to Catalog.
 */
final readonly class FormDraftChanged implements Auditable
{
    public function __construct(
        public OrganizerId $organizerId,
        public EventId $eventId,
        public FormVersionId $formVersionId,
        public int $number,
        public int $fieldCount,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange('form_version', $this->formVersionId->toString(), 'form.draft_changed', $this->organizerId, after: ['number' => $this->number, 'fields' => $this->fieldCount]);
    }
}
