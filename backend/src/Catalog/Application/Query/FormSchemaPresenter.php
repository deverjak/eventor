<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query;

use Eventor\Catalog\Application\Form\FieldTypeRegistry;
use Eventor\Catalog\Domain\Form\FormSchema;

/**
 * The form schema as the API shows it (admin editor and public form): every field with its definition and the JSON
 * Schema fragment of its type, so the frontend renders and mirrors the validation from one source (ADR 007).
 */
final readonly class FormSchemaPresenter
{
    public function __construct(private FieldTypeRegistry $types) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function fields(FormSchema $schema, bool $withJsonSchema = true): array
    {
        $fields = [];
        foreach ($schema->fields as $field) {
            $data = $field->toArray();
            $data['constraints'] = [] === $field->constraints ? new \stdClass() : $field->constraints;
            if ($withJsonSchema) {
                $data['jsonSchema'] = $this->types->get($field->type)->jsonSchema($field);
            }
            $fields[] = $data;
        }

        return $fields;
    }
}
