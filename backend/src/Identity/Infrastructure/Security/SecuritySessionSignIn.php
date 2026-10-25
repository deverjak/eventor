<?php

declare(strict_types=1);

namespace Eventor\Identity\Infrastructure\Security;

use Eventor\Identity\Application\Port\SessionSignIn;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class SecuritySessionSignIn implements SessionSignIn
{
    public function __construct(
        private Security $security,
        private SecurityUserProvider $users,
    ) {}

    public function signIn(string $email): void
    {
        $this->security->login($this->users->loadUserByIdentifier($email), 'security.authenticator.json_login.team', 'team');
    }
}
