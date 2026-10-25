<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\DeviceToken;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Identity\Domain\Event\DeviceTokenIssued;
use Eventor\Identity\Domain\Event\DeviceTokenRevoked;
use Eventor\Shared\Domain\Event\RecordsEvents;
use Eventor\Shared\Domain\Event\RecordsEventsInterface;
use Eventor\Shared\Domain\Id\CheckInListId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\UserAccountId;
use Eventor\Shared\Domain\Tenant\TenantScoped;

/**
 * An opaque credential of a check-in device (ADR 006, CHK-01): bound to an organizer and optionally to one check-in
 * list. Only the SHA-256 of the secret is stored; the full token is shown once, when it is issued.
 */
#[ORM\Entity]
#[ORM\Table(name: 'device_token', schema: 'identity')]
class DeviceToken implements TenantScoped, RecordsEventsInterface
{
    use RecordsEvents;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'device_token_id')]
        private DeviceTokenId $id,
        #[ORM\Column(type: 'organizer_id')]
        private OrganizerId $organizerId,
        #[ORM\Column(type: 'check_in_list_id', nullable: true)]
        private ?CheckInListId $checkInListId,
        #[ORM\Column(type: 'string', length: 100)]
        private string $label,
        #[ORM\Column(type: 'string', length: 64, options: ['fixed' => true])]
        private string $secretSha256,
        #[ORM\Column(type: 'user_account_id')]
        private UserAccountId $createdBy,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $createdAt,
        #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
        private ?\DateTimeImmutable $expiresAt,
    ) {}

    public static function issue(
        DeviceTokenId $id,
        OrganizerId $organizerId,
        ?CheckInListId $checkInListId,
        string $label,
        string $secretSha256,
        UserAccountId $createdBy,
        \DateTimeImmutable $now,
        ?\DateTimeImmutable $expiresAt,
    ): self {
        if (null !== $expiresAt && $expiresAt <= $now) {
            throw DeviceTokenAlreadyExpired::create();
        }

        $token = new self($id, $organizerId, $checkInListId, $label, $secretSha256, $createdBy, $now, $expiresAt);
        $token->record(new DeviceTokenIssued($id, $organizerId, $checkInListId, $label));

        return $token;
    }

    public function id(): DeviceTokenId
    {
        return $this->id;
    }

    public function organizerId(): OrganizerId
    {
        return $this->organizerId;
    }

    public function revoke(\DateTimeImmutable $now): void
    {
        if (null !== $this->revokedAt) {
            return;
        }

        $this->revokedAt = $now;
        $this->record(new DeviceTokenRevoked($this->id, $this->organizerId));
    }

    public function isRevoked(): bool
    {
        return null !== $this->revokedAt;
    }

    public function isActive(\DateTimeImmutable $now): bool
    {
        return null === $this->revokedAt && (null === $this->expiresAt || $this->expiresAt > $now);
    }
}
