<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Form;

/**
 * What a field condition looks at (FRM-05): the selected activities, the selected variants or another answer. A
 * closed set on purpose; the specification excludes a general rule engine.
 */
enum ConditionType: string
{
    case Activity = 'activity';
    case Variant = 'variant';
    case Answer = 'answer';
}
