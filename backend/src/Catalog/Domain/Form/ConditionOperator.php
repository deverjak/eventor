<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Form;

/**
 * Comparison of an `answer` condition.
 */
enum ConditionOperator: string
{
    case Equals = 'equals';
    case NotEquals = 'not_equals';
    case Filled = 'filled';
    case Empty = 'empty';

    public function needsValue(): bool
    {
        return self::Equals === $this || self::NotEquals === $this;
    }
}
