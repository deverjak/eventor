<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Event;

use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\FormVersionId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Domain event: the draft became an immutable form version (FRM-08). `catalog.form_version_published` follows.
 */
final readonly class FormVersionFrozen implements Auditable
{
    /**
     * @param list<string> $addedRequiredFieldKeys required fields that the previous version did not have (S17)
     */
    public function __construct(
        public OrganizerId $organizerId,
        public EventId $eventId,
        public FormVersionId $formVersionId,
        public int $number,
        public ?FormVersionId $previousFormVersionId,
        public array $addedRequiredFieldKeys,
        public string $schemaSha256,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange('form_version', $this->formVersionId->toString(), 'form.version_published', $this->organizerId, after: [
            'number' => $this->number,
            'schemaSha256' => $this->schemaSha256,
            'addedRequiredFields' => $this->addedRequiredFieldKeys,
        ]);
    }
}
