<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Domain;

/**
 * The exception matrix (CHK-09): the first rule that applies decides, so the order of the rules is the order of
 * severity (already checked in, cancelled, wrong activity, needs attention, incomplete, missing confirmation, unpaid).
 * Without an applicable rule the participant may check in.
 */
final readonly class CheckInRuleMatrix
{
    /**
     * @param iterable<CheckInRule> $rules in order of severity
     */
    public function __construct(private iterable $rules) {}

    public function judge(CheckInFacts $facts): CheckInVerdict
    {
        foreach ($this->rules as $rule) {
            $verdict = $rule->judge($facts);
            if (null !== $verdict) {
                return $verdict;
            }
        }

        return CheckInVerdict::allow();
    }
}
