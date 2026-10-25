<?php

declare(strict_types=1);

namespace Eventor\Identity\UserInterface\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body of the public invitation endpoints. The token travels in the body, never in a URL that proxies would log.
 * `password` and `displayName` are needed only when the invited e-mail has no account yet.
 */
final readonly class InvitationTokenRequest
{
    public function __construct(
        #[\SensitiveParameter]
        #[Assert\NotBlank]
        #[Assert\Length(max: 200)]
        public string $token = '',
        #[\SensitiveParameter]
        #[Assert\Length(min: 10, max: 200)]
        public ?string $password = null,
        #[Assert\Length(max: 200)]
        public ?string $displayName = null,
    ) {}
}
