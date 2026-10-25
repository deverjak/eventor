<?php

declare(strict_types=1);

namespace Eventor\Shared\Application\Idempotency;

/**
 * The (decoded) result of the first execution of an idempotent command.
 */
final readonly class StoredResponse
{
    /**
     * @param array<array-key, mixed>|scalar|null $value
     */
    public function __construct(public mixed $value) {}
}
