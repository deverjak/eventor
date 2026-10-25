<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query;

use Eventor\Catalog\Application\Query\View\FieldTypeCatalogView;
use Eventor\Catalog\Application\Query\View\FieldTypeView;
use Eventor\Catalog\Application\Query\View\SemanticFieldView;
use Eventor\Catalog\Contract\AnswerScope;
use Eventor\Catalog\Contract\SemanticField;
use Eventor\Catalog\Domain\Form\FieldTypeName;

/**
 * What the form editor may offer: the field types with their constraint keys, the scopes and the semantic fields.
 */
final readonly class FieldTypeCatalogQuery
{
    public function describe(): FieldTypeCatalogView
    {
        return new FieldTypeCatalogView(
            array_map(static fn(FieldTypeName $type): FieldTypeView => new FieldTypeView($type->value, $type->hasAnswer(), $type->allowedConstraints()), FieldTypeName::cases()),
            array_map(static fn(AnswerScope $scope): string => $scope->value, AnswerScope::cases()),
            array_map(static fn(SemanticField $semantic): SemanticFieldView => new SemanticFieldView(
                $semantic->value,
                array_values(array_map(
                    static fn(FieldTypeName $type): string => $type->value,
                    array_filter(FieldTypeName::cases(), static fn(FieldTypeName $type): bool => $type->carries($semantic)),
                )),
            ), SemanticField::cases()),
        );
    }
}
