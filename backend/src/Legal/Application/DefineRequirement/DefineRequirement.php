<?php

declare(strict_types=1);

namespace Eventor\Legal\Application\DefineRequirement;

use Eventor\Legal\Application\Validation\NoPrecheckedConsent;
use Eventor\Legal\Contract\Confirmer;
use Eventor\Legal\Contract\RequirementType;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Adds a requirement to a document (LEG-04, LEG-05). `revocable` null = the default of the type (voluntary consents
 * are revocable, conditions are not). `preChecked` exists only so that an attempt to pre-tick a voluntary consent
 * is rejected (LEG-06); it is never stored.
 */
#[NoPrecheckedConsent]
final readonly class DefineRequirement
{
    public function __construct(
        #[Assert\Uuid]
        public string $documentId,
        public RequirementType $type,
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
