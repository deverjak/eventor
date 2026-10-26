<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Query\View;

/**
 * A form field that applies to the previewed selection (the full definition with constraints is the form endpoint of
 * the event; this says only what is asked of whom).
 */
final readonly class PreviewFieldView
{
    public function __construct(
        public string $key,
        public string $label,
        public string $type,
        public string $scope,
        public bool $required,
        public ?string $semantic,
    ) {}
}
