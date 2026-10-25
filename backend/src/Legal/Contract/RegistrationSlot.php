<?php

declare(strict_types=1);

namespace Eventor\Legal\Contract;

use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\VariantId;

/**
 * One participation-to-be: `slotRef` is the caller's reference (the `personRef` of the request). `minor` tells the
 * resolver that the participant is a child, so requirements confirmed by the guardian apply to this slot (LEG-11, S13);
 * Registration decides it from the birth date.
 */
final readonly class RegistrationSlot
{
    public function __construct(
        public string $slotRef,
        public ActivityId $activityId,
        public VariantId $variantId,
        public bool $minor = false,
    ) {}
}
