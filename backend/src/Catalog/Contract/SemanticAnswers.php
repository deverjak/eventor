<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract;

/**
 * Semantic fields extracted from an answer set. Personal data: for the caller's transaction only, never for events.
 */
final readonly class SemanticAnswers
{
    public function __construct(
        public ?string $firstName,
        public ?string $lastName,
        public ?\DateTimeImmutable $birthDate,
        public ?string $email,
        public ?string $phone,
        public ?string $guardianFirstName,
        public ?string $guardianLastName,
        public ?string $guardianEmail,
        public ?string $guardianPhone,
    ) {}
}
