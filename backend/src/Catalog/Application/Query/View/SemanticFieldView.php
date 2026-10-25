<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query\View;

final readonly class SemanticFieldView
{
    /**
     * @param list<string> $types field types that can carry it
     */
    public function __construct(
        public string $name,
        public array $types,
    ) {}
}
