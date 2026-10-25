<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\SignUp;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Public self-service registration (ORG-01): a new account, a new organizer and the OWNER membership between them.
 * Unlike the `CreateOwner` bootstrap command it never attaches to an existing account.
 */
final readonly class SignUp
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        #[Assert\Length(max: 255)]
        public string $email,
        #[\SensitiveParameter]
        #[Assert\NotBlank]
        #[Assert\Length(min: 10, max: 200)]
        public string $password,
        #[Assert\NotBlank]
        #[Assert\Length(max: 200)]
        public string $displayName,
        #[Assert\NotBlank]
        #[Assert\Length(max: 200)]
        public string $organizerName,
        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 64)]
        #[Assert\Regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'The slug may contain lower-case letters, digits and single hyphens.')]
        public string $slug,
    ) {}
}
