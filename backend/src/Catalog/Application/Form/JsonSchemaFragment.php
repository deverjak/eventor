<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Form;

use Eventor\Catalog\Domain\Form\FieldDefinition;

/**
 * Capability 3 of a field type: the JSON Schema fragment the frontend renders and mirrors (one source of truth).
 */
interface JsonSchemaFragment
{
    /**
     * @return array<string, mixed>
     */
    public function jsonSchema(FieldDefinition $field): array;
}
