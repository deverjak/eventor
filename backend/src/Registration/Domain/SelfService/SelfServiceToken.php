<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\SelfService;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Registration\Domain\Event\SelfServiceLinkWasRequested;
use Eventor\Shared\Domain\Event\RecordsEvents;
use Eventor\Shared\Domain\Event\RecordsEventsInterface;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\SelfServiceTokenId;
use Eventor\Shared\Domain\Tenant\TenantScoped;

/**
 * The credential of the participant, who has no account (REG-02). The database keeps only the SHA-256 of the secret
 * and the token can be revoked; the secret itself is derived from the token id (see `SelfServiceTokens`), so
 * Communication can rebuild the link whenever it sends a message. Valid until the event ended plus 90 days.
 */
#[ORM\Entity]
#[ORM\Table(name: 'self_service_token', schema: 'registration')]
class SelfServiceToken implements TenantScoped, RecordsEventsInterface
{
    use RecordsEvents;

    public const string SCOPE_ORDER = 'order';

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'self_service_token_id')]
        private SelfServiceTokenId $id,
        #[ORM\Column(type: 'organizer_id')]
        private OrganizerId $organizerId,
        #[ORM\Column(type: 'order_id')]
        private OrderId $orderId,
        #[ORM\Column(name: 'secret_sha256', type: 'string', length: 64, options: ['fixed' => true])]
        private string $secretSha256,
        #[ORM\Column(type: 'string', length: 64)]
        private string $scope,
        #[ORM\Column(type: 'string', length: 32)]
        private string $issuedReason,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $issuedAt,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $expiresAt,
    ) {}

    public function id(): SelfServiceTokenId
    {
        return $this->id;
    }

    public function organizerId(): OrganizerId
    {
        return $this->organizerId;
    }

    public function orderId(): OrderId
    {
        return $this->orderId;
    }

    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isUsable(\DateTimeImmutable $now): bool
    {
        return null === $this->revokedAt && $now < $this->expiresAt;
    }

    public function revoke(\DateTimeImmutable $now): void
    {
        $this->revokedAt ??= $now;
    }

    /** REG-11: the orderer asked for the link again; Communication sends it (the URL is derived from the id). */
    public function noteLinkRequested(): void
    {
        $this->record(new SelfServiceLinkWasRequested($this->organizerId, $this->orderId, $this->id));
    }
}
