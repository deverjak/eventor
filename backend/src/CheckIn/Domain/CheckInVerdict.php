<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Domain;

use Eventor\CheckIn\Contract\CheckInSituation;

/**
 * The outcome of the rule matrix: the decision and, unless it is a plain `Allow`, the situation behind it.
 */
final readonly class CheckInVerdict
{
    private function __construct(
        public CheckInDecision $decision,
        public ?CheckInSituation $situation,
    ) {}

    public static function allow(): self
    {
        return new self(CheckInDecision::Allow, null);
    }

    public static function warn(CheckInSituation $situation): self
    {
        return new self(CheckInDecision::Warn, $situation);
    }

    public static function escalate(CheckInSituation $situation): self
    {
        return new self(CheckInDecision::Escalate, $situation);
    }

    public static function block(CheckInSituation $situation): self
    {
        return new self(CheckInDecision::Block, $situation);
    }
}
