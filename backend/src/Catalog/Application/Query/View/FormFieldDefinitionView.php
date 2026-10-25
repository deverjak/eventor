<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query\View;

/**
 * One field of a form version as the editor and the public form show it: the definition plus the JSON Schema fragment
 * of its type (ADR 007). `constraints`, `condition` and `jsonSchema` are open JSON documents by nature.
 */
final readonly class FormFieldDefinitionView
{
    /**
     * @param array<string, mixed>|\stdClass $constraints `{}` when the field has none
     * @param array<string, mixed>|null      $condition
     * @param list<string>                   $activityIds
     * @param list<string>                   $variantIds
     * @param array<string, mixed>           $jsonSchema
     */
    public function __construct(
        public string $key,
        public string $type,
        public string $label,
        public ?string $help,
        public bool $required,
        public string $scope,
        public array|\stdClass $constraints,
        public ?string $semantic,
        public ?array $condition,
        public array $activityIds,
        public array $variantIds,
        public mixed $defaultValue,
        public array $jsonSchema,
    ) {}
}
