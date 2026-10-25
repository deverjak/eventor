<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\Port;

use Eventor\Shared\Domain\Id\InvitationId;

/**
 * Invitation secrets (ORG-03). A token is derived from the invitation id with a server secret, so only its SHA-256 is
 * stored and the accept link can be rebuilt at delivery time without storing the secret.
 */
interface InvitationTokens
{
    public function issue(InvitationId $id): string;

    /**
     * The invitation id a token claims to belong to (not yet verified against the stored hash), null for garbage.
     */
    public function idOf(string $token): ?InvitationId;

    public function hash(string $token): string;
}
