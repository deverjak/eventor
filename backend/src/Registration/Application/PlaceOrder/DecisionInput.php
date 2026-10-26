<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\PlaceOrder;

use Eventor\Legal\Contract\ConsentDecision;
use Eventor\Shared\Domain\Id\DocumentVersionId;
use Eventor\Shared\Domain\Id\LegalRequirementId;

/**
 * A legal decision from the request. Without `personRef` it is a decision of the orderer for the whole order; with it,
 * a decision about that person's participation (`itemRef` picks one when the person has several items).
 */
final readonly class DecisionInput
{
    public function __construct(
        public LegalRequirementId $requirementId,
        public DocumentVersionId $versionId,
        public ConsentDecision $decision,
        public ?string $personRef = null,
        public ?string $itemRef = null,
    ) {}
}
