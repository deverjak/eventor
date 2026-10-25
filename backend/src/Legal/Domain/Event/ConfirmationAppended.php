<?php

declare(strict_types=1);

namespace Eventor\Legal\Domain\Event;

use Eventor\Legal\Contract\ConfirmationChannel;
use Eventor\Legal\Contract\ConsentDecision;
use Eventor\Legal\Contract\RequirementType;
use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\ConfirmationId;
use Eventor\Shared\Domain\Id\DocumentVersionId;
use Eventor\Shared\Domain\Id\LegalRequirementId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * Domain event: a decision row was appended to the ledger. Audited without personal data (ids only); translated into
 * `legal.confirmation_recorded` and, for a withdrawal, `legal.consent_withdrawn`.
 */
final readonly class ConfirmationAppended implements Auditable
{
    public function __construct(
        public OrganizerId $organizerId,
        public ConfirmationId $confirmationId,
        public LegalRequirementId $requirementId,
        public RequirementType $requirementType,
        public DocumentVersionId $versionId,
        public ConsentDecision $decision,
        public ?ParticipationId $participationId,
        public ?OrderId $orderId,
        public ConfirmationChannel $channel,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange('confirmation', $this->confirmationId->toString(), 'legal.confirmation_'.$this->decision->value, $this->organizerId, after: [
            'requirementId' => $this->requirementId->toString(),
            'versionId' => $this->versionId->toString(),
            'decision' => $this->decision->value,
            'channel' => $this->channel->value,
        ]);
    }
}
