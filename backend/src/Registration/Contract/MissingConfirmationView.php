<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract;

use Eventor\Shared\Domain\Id\LegalRequirementId;

/**
 * A confirmation the participation still owes, in words the check-in desk can show.
 */
final readonly class MissingConfirmationView
{
    public function __construct(
        public LegalRequirementId $requirementId,
        public string $label,
        public bool $mandatory,
    ) {}
}
