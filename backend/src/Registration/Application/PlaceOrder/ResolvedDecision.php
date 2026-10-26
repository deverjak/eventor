<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\PlaceOrder;

use Eventor\Legal\Contract\ApplicableRequirement;

/**
 * A decision of the request matched with the requirement it answers and, for a per-participation requirement, the item.
 */
final readonly class ResolvedDecision
{
    public function __construct(
        public DecisionInput $input,
        public ApplicableRequirement $requirement,
        public ?ResolvedItem $item,
    ) {}
}
