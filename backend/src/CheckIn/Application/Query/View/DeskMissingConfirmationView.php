<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Application\Query\View;

final readonly class DeskMissingConfirmationView
{
    public function __construct(
        public string $label,
        public bool $mandatory,
    ) {}
}
