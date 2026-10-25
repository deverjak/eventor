<?php

declare(strict_types=1);

namespace Eventor\Legal\Application\ArchiveRequirement;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Stops applying a requirement to new registrations. The row stays because confirmations reference it.
 */
final readonly class ArchiveRequirement
{
    public function __construct(
        #[Assert\Uuid]
        public string $requirementId,
    ) {}
}
