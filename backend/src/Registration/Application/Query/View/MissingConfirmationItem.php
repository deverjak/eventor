<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Query\View;

use Eventor\Shared\Domain\Id\LegalRequirementId;

/**
 * A confirmation a participation still owes, in the words of the document (S15: `reconfirmation` = granted before, a
 * newer version asks again).
 */
final readonly class MissingConfirmationItem
{
    public function __construct(
        public LegalRequirementId $requirementId,
        public string $title,
        public bool $mandatory,
        public bool $reconfirmation,
    ) {}
}
