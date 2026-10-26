<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\PlaceOrder;

use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\VariantId;
use Eventor\Shared\Domain\Id\WaveId;

/**
 * One participation-to-be: a person in an activity and variant. `itemRef` names the item in the legal decisions of the
 * same request; it defaults to the `personRef` when the person has exactly one item.
 */
final readonly class OrderItemInput
{
    public function __construct(
        public string $personRef,
        public ActivityId $activityId,
        public VariantId $variantId,
        public ?WaveId $waveId = null,
        public ?string $itemRef = null,
    ) {}
}
