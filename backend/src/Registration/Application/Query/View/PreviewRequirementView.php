<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Query\View;

use Eventor\Legal\Contract\Confirmer;
use Eventor\Legal\Contract\RequirementType;
use Eventor\Shared\Domain\Id\DocumentVersionId;
use Eventor\Shared\Domain\Id\LegalDocumentId;
use Eventor\Shared\Domain\Id\LegalRequirementId;

/**
 * A legal requirement to confirm: the ids the order request must echo back (`requirementId`, `documentVersionId`).
 */
final readonly class PreviewRequirementView
{
    public function __construct(
        public LegalRequirementId $requirementId,
        public LegalDocumentId $documentId,
        public DocumentVersionId $documentVersionId,
        public RequirementType $type,
        public string $title,
        public string $purpose,
        public Confirmer $confirmer,
        public bool $mandatory,
        public bool $revocable,
    ) {}
}
