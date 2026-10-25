<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Identity\Application\Port\InvitationTokens;
use Eventor\Identity\Application\Query\View\InvitationPreviewView;
use Psr\Clock\ClockInterface;

/**
 * What the accept page shows before the person commits: organizer, role and whether the invited e-mail already has an
 * account (then the person signs in instead of choosing a password). Null for any token that is not usable.
 */
final readonly class InvitationPreviewQuery
{
    public function __construct(
        private Connection $connection,
        private InvitationTokens $tokens,
        private ClockInterface $clock,
    ) {}

    /**
     * Null for any token that is not usable.
     */
    public function preview(string $token): ?InvitationPreviewView
    {
        $id = $this->tokens->idOf($token);
        if (null === $id) {
            return null;
        }

        /** @var array{email: string, role: string, expires_at: string, name: string, slug: string, token_sha256: string, account_exists: bool}|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT i.email, i.role, i.expires_at, i.token_sha256, o.name, o.slug,
                    EXISTS (SELECT 1 FROM identity.user_account u WHERE u.email = i.email) AS account_exists
               FROM identity.invitation i
               JOIN identity.organizer o ON o.id = i.organizer_id
              WHERE i.id = :id AND i.accepted_at IS NULL AND i.revoked_at IS NULL AND i.expires_at > :now',
            ['id' => $id->toString(), 'now' => $this->clock->now()->format(\DATE_ATOM)],
        );
        if (false === $row || !hash_equals($row['token_sha256'], $this->tokens->hash($token))) {
            return null;
        }

        return new InvitationPreviewView(
            $row['name'],
            $row['slug'],
            $row['email'],
            $row['role'],
            (bool) $row['account_exists'],
            (new \DateTimeImmutable($row['expires_at']))->format(\DATE_ATOM),
        );
    }
}
