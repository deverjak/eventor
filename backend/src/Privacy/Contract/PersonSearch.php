<?php

declare(strict_types=1);

namespace Eventor\Privacy\Contract;

/**
 * Search criteria of a data subject request; at least one must be set.
 */
final readonly class PersonSearch
{
    public function __construct(
        public ?string $email,
        public ?string $name,
        public ?\DateTimeImmutable $birthDate,
    ) {
        if (null === $email && null === $name && null === $birthDate) {
            throw new \InvalidArgumentException('Search by at least one criterion.');
        }
    }
}
