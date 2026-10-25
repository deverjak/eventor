<?php

declare(strict_types=1);

namespace Eventor\Shared\Application\Security;

/**
 * Implemented by the authenticated principal of a team member. Audit and other cross-cutting code identify the
 * actor by account id (never by e-mail, which is personal data and the Symfony user identifier).
 */
interface IdentifiableUser
{
    public function accountId(): string;
}
