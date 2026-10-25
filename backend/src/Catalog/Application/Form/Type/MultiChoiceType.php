<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Form\Type;

use Eventor\Catalog\Application\Form\AsFieldType;
use Eventor\Catalog\Domain\Form\FieldDefinition;
use Symfony\Component\Validator\Constraints as Assert;

#[AsFieldType('multi_choice')]
final class MultiChoiceType extends ChoiceType
{
    public function constraints(FieldDefinition $field): array
    {
        return [
            new Assert\Type('array'),
            new Assert\Choice(
                choices: $this->values($field),
                multiple: true,
                min: $this->nonNegativeInt($field, 'minChoices'),
                max: $this->positiveInt($field, 'maxChoices'),
            ),
        ];
    }

    public function normalize(FieldDefinition $field, mixed $value): mixed
    {
        return \is_array($value) ? array_values(array_unique($value, \SORT_REGULAR)) : $value;
    }

    public function jsonSchema(FieldDefinition $field): array
    {
        $schema = ['type' => 'array', 'uniqueItems' => true, 'items' => ['oneOf' => $this->options($field)]];
        foreach (['minChoices' => 'minItems', 'maxChoices' => 'maxItems'] as $from => $to) {
            if (null !== $this->intConstraint($field, $from)) {
                $schema[$to] = $this->intConstraint($field, $from);
            }
        }

        return $this->describe($field, $schema);
    }
}
