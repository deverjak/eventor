<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract;

/**
 * One invalid answer. `path` is the field path in the request (`persons[1].birth_date`).
 */
final readonly class FormViolation
{
    public function __construct(
        public string $path,
        public string $code,
        public string $message,
    ) {}
}
