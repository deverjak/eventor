<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Form;

use Eventor\Catalog\Domain\Form\FieldDefinition;
use Symfony\Component\Validator\Constraint;

/**
 * Capability 1 of a field type: the Symfony Validator constraints that an answer must satisfy (FRM-02, FRM-07).
 */
interface ConstraintFactory
{
    /**
     * @return list<Constraint>
     */
    public function constraints(FieldDefinition $field): array;
}
