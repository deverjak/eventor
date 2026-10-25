<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query\View;

/**
 * The current registration form of a published event.
 */
final readonly class PublicFormView
{
    /**
     * @param list<FormFieldDefinitionView> $fields
     */
    public function __construct(
        public string $formVersionId,
        public int $number,
        public string $schemaSha256,
        public array $fields,
    ) {}
}
