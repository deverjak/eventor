<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Domain;

/**
 * One row of the exception matrix (CHK-09). A rule returns a verdict when it applies and null when it has nothing to
 * say. Rules are tagged `eventor.check_in_rule` and ordered by priority, most severe first.
 */
interface CheckInRule
{
    public function judge(CheckInFacts $facts): ?CheckInVerdict;
}
