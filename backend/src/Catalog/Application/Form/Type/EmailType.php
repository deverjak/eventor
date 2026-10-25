<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Form\Type;

use Eventor\Catalog\Application\Form\AsFieldType;
use Eventor\Catalog\Domain\Form\FieldDefinition;
use Symfony\Component\Validator\Constraints as Assert;

#[AsFieldType('email')]
final class EmailType extends AbstractFieldType
{
    public function constraints(FieldDefinition $field): array
    {
        return [new Assert\Type('string'), new Assert\Length(max: 255), new Assert\Email()];
    }

    public function normalize(FieldDefinition $field, mixed $value): mixed
    {
        return \is_string($value) ? strtolower(trim($value)) : $value;
    }

    public function jsonSchema(FieldDefinition $field): array
    {
        return $this->describe($field, ['type' => 'string', 'format' => 'email', 'maxLength' => 255]);
    }
}
