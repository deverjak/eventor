<?php

declare(strict_types=1);

namespace Eventor\Legal\Domain\Requirement;

use Eventor\Shared\Domain\Id\LegalDocumentId;
use Eventor\Shared\Domain\Id\LegalRequirementId;

interface LegalRequirementRepository
{
    public function find(LegalRequirementId $id): ?LegalRequirement;

    public function add(LegalRequirement $requirement): void;

    /**
     * Requirements of the document that are not archived.
     *
     * @return list<LegalRequirement>
     */
    public function activeOfDocument(LegalDocumentId $document): array;
}
