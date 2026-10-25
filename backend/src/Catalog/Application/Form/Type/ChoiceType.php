<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Form\Type;

use Eventor\Catalog\Domain\Form\FieldDefinition;

/**
 * The list of choices of a choice field, shared by the single and the multiple variant.
 */
abstract class ChoiceType extends AbstractFieldType
{
    /**
     * @return list<string|int>
     */
    protected function values(FieldDefinition $field): array
    {
        return array_column($this->options($field), 'const');
    }

    /**
     * @return list<array{const: string|int, title: string}>
     */
    protected function options(FieldDefinition $field): array
    {
        $options = [];
        $choices = $field->constraints['choices'] ?? [];
        foreach (\is_array($choices) ? $choices : [] as $choice) {
            if (\is_array($choice) && (\is_string($choice['value'] ?? null) || \is_int($choice['value'] ?? null))) {
                $options[] = ['const' => $choice['value'], 'title' => \is_string($choice['label'] ?? null) ? $choice['label'] : (string) $choice['value']];
            }
        }

        return $options;
    }
}
