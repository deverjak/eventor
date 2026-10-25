<?php

declare(strict_types=1);

namespace Eventor\Legal\Application\Validation;

use Symfony\Component\Validator\Constraint;

/**
 * Class-level constraint for requirement configuration (LEG-06): a voluntary consent (`consent`, `likeness`) can be
 * neither pre-checked nor mandatory, and must be revocable. The object needs the properties `type`, `mandatory`,
 * `preChecked` and optionally `revocable` (null = the default for the type).
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class NoPrecheckedConsent extends Constraint
{
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
