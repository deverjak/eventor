<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Form\Type;

use Eventor\Catalog\Application\Form\AnswerNormalizer;
use Eventor\Catalog\Application\Form\ConstraintFactory;
use Eventor\Catalog\Application\Form\JsonSchemaFragment;
use Eventor\Catalog\Domain\Form\FieldDefinition;

/**
 * Shared shape of the field types: the title/description of the JSON schema comes from label and help, the stored
 * value is the validated one unless a type canonicalises it.
 */
abstract class AbstractFieldType implements ConstraintFactory, AnswerNormalizer, JsonSchemaFragment
{
    public function normalize(FieldDefinition $field, mixed $value): mixed
    {
        return $value;
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    protected function describe(FieldDefinition $field, array $schema): array
    {
        $schema['title'] = $field->label;
        if (null !== $field->help) {
            $schema['description'] = $field->help;
        }
        if (null !== $field->defaultValue) {
            $schema['default'] = $field->defaultValue;
        }

        return $schema;
    }

    protected function intConstraint(FieldDefinition $field, string $name): ?int
    {
        $value = $field->constraints[$name] ?? null;

        return \is_int($value) ? $value : null;
    }
}
