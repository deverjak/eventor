<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query\View;

/**
 * What the form editor may offer.
 */
final readonly class FieldTypeCatalogView
{
    /**
     * @param list<FieldTypeView>     $types
     * @param list<string>            $scopes
     * @param list<SemanticFieldView> $semanticFields
     */
    public function __construct(
        public array $types,
        public array $scopes,
        public array $semanticFields,
    ) {}
}
