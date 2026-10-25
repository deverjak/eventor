<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Form\Type;

use Eventor\Catalog\Application\Form\AsFieldType;
use Eventor\Catalog\Domain\Form\FieldDefinition;

/**
 * Display-only text: no answer is accepted or stored, so it has no constraints.
 */
#[AsFieldType('info_text')]
final class InfoTextType extends AbstractFieldType
{
    public function constraints(FieldDefinition $field): array
    {
        return [];
    }

    public function jsonSchema(FieldDefinition $field): array
    {
        return $this->describe($field, ['readOnly' => true, 'type' => 'null']);
    }
}
