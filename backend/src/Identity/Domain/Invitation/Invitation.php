<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\Invitation;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Identity\Contract\Exception\InvitationNotFound;
use Eventor\Identity\Domain\Event\InvitationAccepted;
use Eventor\Identity\Domain\Event\InvitationCreated;
use Eventor\Identity\Domain\Event\InvitationRevoked;
use Eventor\Identity\Domain\Membership\InvalidEventScope;
use Eventor\Identity\Domain\Membership\Role;
use Eventor\Shared\Domain\Event\RecordsEvents;
use Eventor\Shared\Domain\Event\RecordsEventsInterface;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\InvitationId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\UserAccountId;

/**
 * A team invitation (ORG-03): "this e-mail may join the organizer with this role (and event scope) until it expires".
 * Only the SHA-256 of the token is stored. Not tenant-scoped: the accepting person has no session and no tenant yet,
 * so admin queries filter by organizer explicitly.
 */
#[ORM\Entity]
#[ORM\Table(name: 'invitation', schema: 'identity')]
class Invitation implements RecordsEventsInterface
{
    use RecordsEvents;

    public const string TTL = '+7 days';

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $acceptedAt = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    /**
     * @param list<string> $eventIds
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'invitation_id')]
        private InvitationId $id,
        #[ORM\Column(type: 'organizer_id')]
        private OrganizerId $organizerId,
        #[ORM\Column(type: 'string', length: 255)]
        private string $email,
        #[ORM\Column(type: 'string', length: 32, enumType: Role::class)]
        private Role $role,
        #[ORM\Column(type: 'json', options: ['jsonb' => true])]
        private array $eventIds,
        #[ORM\Column(type: 'string', length: 64, options: ['fixed' => true])]
        private string $tokenSha256,
        #[ORM\Column(type: 'user_account_id')]
        private UserAccountId $invitedBy,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $createdAt,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $expiresAt,
    ) {}

    /**
     * @param list<EventId> $eventScope
     */
    public static function create(
        InvitationId $id,
        OrganizerId $organizerId,
        string $email,
        Role $role,
        array $eventScope,
        string $tokenSha256,
        UserAccountId $invitedBy,
        \DateTimeImmutable $now,
    ): self {
        $ids = array_values(array_unique(array_map(static fn(EventId $e): string => $e->toString(), $eventScope)));
        if ([] !== $ids && !\in_array($role, [Role::EventManager, Role::CheckIn], true)) {
            throw InvalidEventScope::notAllowedFor($role);
        }

        $invitation = new self($id, $organizerId, mb_strtolower(trim($email)), $role, $ids, $tokenSha256, $invitedBy, $now, $now->modify(self::TTL));
        $invitation->record(new InvitationCreated($id, $organizerId, $role));

        return $invitation;
    }

    public function id(): InvitationId
    {
        return $this->id;
    }

    public function organizerId(): OrganizerId
    {
        return $this->organizerId;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function role(): Role
    {
        return $this->role;
    }

    /**
     * @return list<EventId>
     */
    public function eventScope(): array
    {
        return array_map(static fn(string $id): EventId => EventId::fromString($id), $this->eventIds);
    }

    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function tokenSha256(): string
    {
        return $this->tokenSha256;
    }

    public function isRevoked(): bool
    {
        return null !== $this->revokedAt;
    }

    public function isPending(\DateTimeImmutable $now): bool
    {
        return null === $this->acceptedAt && null === $this->revokedAt && $this->expiresAt > $now;
    }

    /**
     * @throws InvitationNotFound when it was already used, revoked or has expired
     */
    public function accept(UserAccountId $by, \DateTimeImmutable $now): void
    {
        if (!$this->isPending($now)) {
            throw InvitationNotFound::forId($this->id);
        }

        $this->acceptedAt = $now;
        $this->record(new InvitationAccepted($this->id, $this->organizerId, $by));
    }

    public function revoke(\DateTimeImmutable $now): void
    {
        if (null !== $this->acceptedAt) {
            throw InvitationNotFound::forId($this->id);
        }
        if (null !== $this->revokedAt) {
            return;
        }

        $this->revokedAt = $now;
        $this->record(new InvitationRevoked($this->id, $this->organizerId));
    }
}
