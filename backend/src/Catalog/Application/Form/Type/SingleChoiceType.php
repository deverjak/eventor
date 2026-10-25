<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Form\Type;

use Eventor\Catalog\Application\Form\AsFieldType;
use Eventor\Catalog\Domain\Form\FieldDefinition;
use Symfony\Component\Validator\Constraints as Assert;

#[AsFieldType('single_choice')]
final class SingleChoiceType extends ChoiceType
{
    public function constraints(FieldDefinition $field): array
    {
        return [new Assert\Choice(choices: $this->values($field))];
    }

    public function jsonSchema(FieldDefinition $field): array
    {
        return $this->describe($field, ['oneOf' => $this->options($field)]);
    }
}
