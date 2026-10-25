<?php

declare(strict_types=1);

namespace Eventor\Legal\Contract;

use Eventor\Shared\Domain\Id\DocumentVersionId;
use Eventor\Shared\Domain\Id\LegalRequirementId;

/**
 * A decision from the request; `slotRef` null = a decision of the orderer for the whole order.
 */
final readonly class SubmittedDecision
{
    public function __construct(
        public LegalRequirementId $requirementId,
        public DocumentVersionId $versionId,
        public ?string $slotRef,
        public ConsentDecision $decision,
    ) {}
}
