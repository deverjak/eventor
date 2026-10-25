<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Form\Type;

use Eventor\Catalog\Domain\Form\FieldDefinition;
use Eventor\Catalog\Domain\Form\FieldTypeName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Shared implementation of the two free-text types; the registered classes only differ in their default limit.
 */
abstract class TextType extends AbstractFieldType
{
    abstract protected function defaultMaxLength(): int;

    public function constraints(FieldDefinition $field): array
    {
        return [
            new Assert\Type('string'),
            new Assert\Length(
                min: $this->nonNegativeInt($field, 'minLength'),
                max: $this->positiveInt($field, 'maxLength') ?? max(1, $this->defaultMaxLength()),
            ),
        ];
    }

    public function normalize(FieldDefinition $field, mixed $value): mixed
    {
        return \is_string($value) ? trim($value) : $value;
    }

    public function jsonSchema(FieldDefinition $field): array
    {
        $schema = ['type' => 'string', 'maxLength' => $this->intConstraint($field, 'maxLength') ?? $this->defaultMaxLength()];
        if (null !== $this->intConstraint($field, 'minLength')) {
            $schema['minLength'] = $this->intConstraint($field, 'minLength');
        }
        if (FieldTypeName::LongText === $field->type) {
            $schema['x-multiline'] = true;
        }

        return $this->describe($field, $schema);
    }
}
