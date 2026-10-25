<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\Port;

/**
 * Signs an account in programmatically (session cookie, session id migration), without a password check: used right
 * after sign-up and after accepting an invitation with account creation, where the request itself proved the identity.
 */
interface SessionSignIn
{
    public function signIn(string $email): void;
}
