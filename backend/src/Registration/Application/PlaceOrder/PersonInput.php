<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\PlaceOrder;

/**
 * A person of the order with the raw answers of the person-scoped form fields.
 */
final readonly class PersonInput
{
    /**
     * @param array<string, mixed> $answers
     */
    public function __construct(
        public string $personRef,
        public array $answers,
    ) {}
}
