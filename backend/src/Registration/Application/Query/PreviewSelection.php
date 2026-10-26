<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Query;

use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\VariantId;

/**
 * One chosen combination in a form preview: an activity and variant for a participant who is (or is not) a minor.
 */
final readonly class PreviewSelection
{
    public function __construct(
        public string $itemRef,
        public ActivityId $activityId,
        public VariantId $variantId,
        public bool $minor = false,
    ) {}
}
