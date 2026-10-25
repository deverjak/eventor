<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Form\Type;

use Eventor\Catalog\Application\Form\AsFieldType;
use Eventor\Catalog\Domain\Form\FieldDefinition;
use Symfony\Component\Validator\Constraints as Assert;

#[AsFieldType('number')]
final class NumberType extends AbstractFieldType
{
    public function constraints(FieldDefinition $field): array
    {
        $constraints = [new Assert\Type(true === ($field->constraints['integer'] ?? false) ? 'int' : ['int', 'float'])];
        $min = $field->constraints['min'] ?? null;
        $max = $field->constraints['max'] ?? null;
        $min = \is_int($min) || \is_float($min) ? $min : null;
        $max = \is_int($max) || \is_float($max) ? $max : null;
        if (null !== $min || null !== $max) {
            $constraints[] = new Assert\Range(min: $min, max: $max);
        }

        return $constraints;
    }

    public function jsonSchema(FieldDefinition $field): array
    {
        $schema = ['type' => true === ($field->constraints['integer'] ?? false) ? 'integer' : 'number'];
        foreach (['min' => 'minimum', 'max' => 'maximum'] as $from => $to) {
            if (isset($field->constraints[$from])) {
                $schema[$to] = $field->constraints[$from];
            }
        }

        return $this->describe($field, $schema);
    }
}
