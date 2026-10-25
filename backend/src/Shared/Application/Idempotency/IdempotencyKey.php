<?php

declare(strict_types=1);

namespace Eventor\Shared\Application\Idempotency;

/**
 * Validated value of the `Idempotency-Key` header: a client-generated UUID (or any 16-128 character token of
 * `[A-Za-z0-9_.:-]`). IdempotencyKeyResolver builds it for controller arguments of this type.
 */
final readonly class IdempotencyKey
{
    public string $value;

    public function __construct(string $value)
    {
        if (1 !== preg_match('/^[A-Za-z0-9_.:-]{16,128}$/D', $value)) {
            throw InvalidIdempotencyKey::malformed();
        }

        $this->value = $value;
    }
}
