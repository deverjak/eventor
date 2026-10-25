<?php

declare(strict_types=1);

namespace Eventor\Legal\Application\ChangeRequirement;

use Eventor\Legal\Contract\Confirmer;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Replaces the editable properties of a requirement. The type and the document are fixed. Existing confirmations
 * stay as they are; only future registrations see the new configuration.
 */
final readonly class ChangeRequirement
{
    public function __construct(
        #[Assert\Uuid]
        public string $requirementId,
        #[Assert\NotBlank]
        #[Assert\Length(max: 500)]
        public string $purpose,
        public Confirmer $confirmer = Confirmer::EachParticipant,
        public bool $mandatory = false,
        public ?bool $revocable = null,
        public bool $preChecked = false,
        public ?string $scopeText = null,
        #[Assert\Length(max: 200)]
        public ?string $validity = null,
        #[Assert\Uuid]
        public ?string $activityId = null,
        #[Assert\Uuid]
        public ?string $variantId = null,
    ) {}
}
