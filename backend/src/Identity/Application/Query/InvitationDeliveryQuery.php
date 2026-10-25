<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Identity\Application\Port\InvitationTokens;
use Eventor\Identity\Contract\Exception\InvitationNotFound;
use Eventor\Identity\Contract\InvitationDeliveryView;
use Eventor\Identity\Contract\InvitationQuery;
use Eventor\Shared\Domain\Id\InvitationId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * What Communication needs to deliver an invitation. The accept URL is rebuilt from the invitation id (the token is
 * derived, never stored) and points at the frontend: `{EVENTOR_PUBLIC_URL}/invitations/accept?token=...`.
 */
#[AsAlias(InvitationQuery::class)]
final readonly class InvitationDeliveryQuery implements InvitationQuery
{
    public function __construct(
        private Connection $connection,
        private InvitationTokens $tokens,
        private ClockInterface $clock,
        #[Autowire('%env(EVENTOR_PUBLIC_URL)%')]
        private string $publicUrl,
    ) {}

    public function forDelivery(OrganizerId $organizer, InvitationId $invitation): InvitationDeliveryView
    {
        /** @var array{email: string, role: string, expires_at: string, name: string}|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT i.email, i.role, i.expires_at, o.name
               FROM identity.invitation i
               JOIN identity.organizer o ON o.id = i.organizer_id
              WHERE i.id = :id AND i.organizer_id = :organizer AND i.accepted_at IS NULL AND i.revoked_at IS NULL AND i.expires_at > :now',
            ['id' => $invitation->toString(), 'organizer' => $organizer->toString(), 'now' => $this->clock->now()->format(\DATE_ATOM)],
        );
        if (false === $row) {
            throw InvitationNotFound::forId($invitation);
        }

        return new InvitationDeliveryView(
            $invitation,
            $organizer,
            $row['email'],
            $row['role'],
            $row['name'],
            rtrim($this->publicUrl, '/').'/invitations/accept?token='.$this->tokens->issue($invitation),
            new \DateTimeImmutable($row['expires_at']),
        );
    }
}
