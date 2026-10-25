<?php

declare(strict_types=1);

namespace Eventor\Legal\Contract;

use Eventor\Shared\Domain\Id\DocumentVersionId;
use Eventor\Shared\Domain\Id\LegalDocumentId;
use Eventor\Shared\Domain\Id\LegalRequirementId;

/**
 * A requirement with the document version that is current now. `confirmer` tells who has to decide: the orderer once,
 * each participant, or the guardian of a minor (LEG-11). Optional requirements (`mandatory` false) may be declined.
 */
final readonly class ApplicableRequirement
{
    public function __construct(
        public LegalRequirementId $requirementId,
        public LegalDocumentId $documentId,
        public DocumentVersionId $versionId,
        public RequirementType $type,
        public string $purpose,
        public Confirmer $confirmer,
        public bool $mandatory,
        public bool $revocable,
        public string $documentTitle,
    ) {}
}
