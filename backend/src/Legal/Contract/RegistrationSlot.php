<?php

declare(strict_types=1);

namespace Eventor\Legal\Contract;

use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\VariantId;

/**
 * One participation-to-be: `slotRef` is the caller's reference (the `personRef` of the request).
 */
final readonly class RegistrationSlot
{
    public function __construct(
        public string $slotRef,
        public ActivityId $activityId,
        public VariantId $variantId,
    ) {}
}
