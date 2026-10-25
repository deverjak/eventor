<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract;

/**
 * Where a transactional message goes: the orderer. Personal data, never copied onto the bus or into logs.
 */
final readonly class RecipientContact
{
    public function __construct(
        public string $email,
        public string $displayName,
        public string $locale,
    ) {}
}
