<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Form\Type;

use Eventor\Catalog\Application\Form\AsFieldType;
use Eventor\Catalog\Domain\Form\FieldDefinition;
use Symfony\Component\Validator\Constraints as Assert;

#[AsFieldType('yes_no')]
final class YesNoType extends AbstractFieldType
{
    public function constraints(FieldDefinition $field): array
    {
        return [new Assert\Type('bool')];
    }

    public function jsonSchema(FieldDefinition $field): array
    {
        return $this->describe($field, ['type' => 'boolean']);
    }
}
