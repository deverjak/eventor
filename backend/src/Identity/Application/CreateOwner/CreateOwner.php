<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\CreateOwner;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Creates an organizer together with its first OWNER (bootstrap, demo and tests: public sign-up ORG-01 is separate).
 * When the e-mail already belongs to an account, that account becomes the owner of the new organizer and its
 * password is left untouched.
 */
final readonly class CreateOwner
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
