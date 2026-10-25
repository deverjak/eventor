<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Presenter;

use Eventor\Catalog\Application\Form\FieldTypeRegistry;
use Eventor\Catalog\Application\Query\View\FormFieldDefinitionView;
use Eventor\Catalog\Domain\Form\FormSchema;

/**
 * Builds the fields of a form schema as the API shows them (admin editor and public form): every field with its
 * definition and the JSON Schema fragment of its type, so the frontend renders and mirrors the validation from one
 * source (ADR 007).
 */
final readonly class FormSchemaPresenter
{
    public function __construct(private FieldTypeRegistry $types) {}

    /**
     * @return list<FormFieldDefinitionView>
     */
    public function fields(FormSchema $schema): array
    {
        $fields = [];
        foreach ($schema->fields as $field) {
            $fields[] = new FormFieldDefinitionView(
                $field->key,
                $field->type->value,
                $field->label,
                $field->help,
                $field->required,
                $field->scope->value,
                [] === $field->constraints ? new \stdClass() : $field->constraints,
                $field->semantic?->value,
                $field->condition?->toArray(),
                $field->activityIds,
                $field->variantIds,
                $field->defaultValue,
                $this->types->get($field->type)->jsonSchema($field),
            );
        }

        return $fields;
    }
}
