<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Form;

use Eventor\Catalog\Contract\AnswerScope;
use Eventor\Catalog\Contract\SemanticField;
use Eventor\Catalog\Domain\Form\FieldTypeName;

/**
 * What the form editor may offer: the field types with their constraint keys, the scopes and the semantic fields.
 */
final readonly class FieldTypeCatalog
{
    /**
     * @return array{types: list<array<string, mixed>>, scopes: list<string>, semanticFields: list<array<string, mixed>>}
     */
    public function describe(): array
    {
        return [
            'types' => array_map(static fn(FieldTypeName $type): array => [
                'name' => $type->value,
                'carriesAnswer' => $type->hasAnswer(),
                'constraints' => $type->allowedConstraints(),
            ], FieldTypeName::cases()),
            'scopes' => array_map(static fn(AnswerScope $scope): string => $scope->value, AnswerScope::cases()),
            'semanticFields' => array_map(static function (SemanticField $semantic): array {
                return [
                    'name' => $semantic->value,
                    'types' => array_values(array_map(
                        static fn(FieldTypeName $type): string => $type->value,
                        array_filter(FieldTypeName::cases(), static fn(FieldTypeName $type): bool => $type->carries($semantic)),
                    )),
                ];
            }, SemanticField::cases()),
        ];
    }
}
