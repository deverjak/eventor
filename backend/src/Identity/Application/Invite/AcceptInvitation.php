<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\Invite;

/**
 * Accepts a team invitation (ORG-03), either as the signed-in account the invitation was sent to, or anonymously with
 * a password, which creates the account. `signedInEmail` is the Symfony user identifier of the current session.
 */
final readonly class AcceptInvitation
{
    public function __construct(
        #[\SensitiveParameter]
        public string $token,
        public ?string $signedInEmail,
        #[\SensitiveParameter]
        public ?string $password,
        public ?string $displayName,
    ) {}
}
