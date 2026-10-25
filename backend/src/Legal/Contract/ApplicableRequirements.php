<?php

declare(strict_types=1);

namespace Eventor\Legal\Contract;

/**
 * Requirements of the whole order and per slot (keyed by `slotRef`).
 */
final readonly class ApplicableRequirements
{
    /**
     * @param list<ApplicableRequirement> $orderLevel
     * @param array<string, list<ApplicableRequirement>> $perSlot
     */
    public function __construct(
        public array $orderLevel,
        public array $perSlot,
    ) {}
}
