<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query\View;

final readonly class FieldTypeView
{
    /**
     * @param list<string> $constraints
     */
    public function __construct(
        public string $name,
        public bool $carriesAnswer,
        public array $constraints,
    ) {}
}
