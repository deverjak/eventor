<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Form;

use Eventor\Catalog\Domain\Form\FieldDefinition;

/**
 * Capability 2 of a field type: the canonical stored value of a validated answer (ISO dates, trimmed text,
 * `+420` phone numbers, choice keys and not labels).
 */
interface AnswerNormalizer
{
    public function normalize(FieldDefinition $field, mixed $value): mixed;
}
