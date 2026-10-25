<?php

declare(strict_types=1);

namespace Eventor\Identity\Infrastructure\Security;

use Eventor\Identity\Application\Port\InvitationTokens;
use Eventor\Shared\Domain\Id\InvitationId;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * `inv1_<invitation id>_<base64url(HMAC-SHA256(secret, "invitation|" . id))>`. 256 bits of unguessable MAC; the
 * database holds only `sha256(token)`, so a database leak does not yield working links.
 */
final readonly class HmacInvitationTokens implements InvitationTokens
{
    private const string PREFIX = 'inv1_';

    public function __construct(#[Autowire('%kernel.secret%')] private string $secret) {}

    public function issue(InvitationId $id): string
    {
        $mac = hash_hmac('sha256', 'invitation|'.$id->toString(), $this->secret, true);

        return self::PREFIX.$id->toString().'_'.rtrim(strtr(base64_encode($mac), '+/', '-_'), '=');
    }

    public function idOf(string $token): ?InvitationId
    {
        if (1 !== preg_match('/^inv1_([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})_[A-Za-z0-9_-]{43}$/', $token, $m)) {
            return null;
        }

        return InvitationId::fromString($m[1]);
    }

    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
