<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Form\Type;

use Eventor\Catalog\Application\Form\AsFieldType;
use Eventor\Catalog\Application\Form\Constraint\ValidCzechPhone;
use Eventor\Catalog\Application\Form\Constraint\ValidCzechPhoneValidator;
use Eventor\Catalog\Domain\Form\FieldDefinition;
use Symfony\Component\Validator\Constraints as Assert;

#[AsFieldType('phone')]
final class PhoneType extends AbstractFieldType
{
    public function constraints(FieldDefinition $field): array
    {
        return [new Assert\Type('string'), new ValidCzechPhone()];
    }

    public function normalize(FieldDefinition $field, mixed $value): mixed
    {
        return \is_string($value) ? (ValidCzechPhoneValidator::canonical($value) ?? $value) : $value;
    }

    public function jsonSchema(FieldDefinition $field): array
    {
        return $this->describe($field, ['type' => 'string', 'format' => 'tel', 'pattern' => '^(\\+420|00420)?\\s?[1-9][0-9]{2}\\s?[0-9]{3}\\s?[0-9]{3}$']);
    }
}
