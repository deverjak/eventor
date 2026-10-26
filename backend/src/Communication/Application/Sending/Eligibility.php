<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Sending;

final readonly class Eligibility
{
    private function __construct(
        public bool $eligible,
        public ?string $reason,
    ) {}

    public static function yes(): self
    {
        return new self(true, null);
    }

    /**
     * @param string $reason stable code shown in the delivery history: `already_paid`, `not_in_audience`...
     */
    public static function no(string $reason): self
    {
        return new self(false, $reason);
    }
}
