<?php

declare(strict_types=1);

namespace Eventor\Legal\Contract;

use Eventor\Shared\Domain\Id\LegalRequirementId;

/**
 * A requirement a participation still owes. `reconfirmation` = granted before, a newer version asks again (S15).
 */
final readonly class MissingConfirmation
{
    public function __construct(
        public LegalRequirementId $requirementId,
        public RequirementType $type,
        public string $documentTitle,
        public bool $mandatory,
        public bool $reconfirmation,
    ) {}
}
