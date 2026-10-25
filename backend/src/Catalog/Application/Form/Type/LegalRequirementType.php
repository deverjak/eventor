<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Form\Type;

use Eventor\Catalog\Application\Form\AsFieldType;
use Eventor\Catalog\Domain\Form\FieldDefinition;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Places a legal requirement (LEG) in the form: the registrant ticks it. The decision itself is recorded by Legal;
 * a required one must be ticked.
 */
#[AsFieldType('legal_requirement')]
final class LegalRequirementType extends AbstractFieldType
{
    public function constraints(FieldDefinition $field): array
    {
        return $field->required
            ? [new Assert\Type('bool'), new Assert\IsTrue(message: 'This confirmation is required.')]
            : [new Assert\Type('bool')];
    }

    public function jsonSchema(FieldDefinition $field): array
    {
        $schema = ['type' => 'boolean'];
        if (\is_string($field->constraints['requirementId'] ?? null)) {
            $schema['x-requirement-id'] = $field->constraints['requirementId'];
        }

        return $this->describe($field, $schema);
    }
}
