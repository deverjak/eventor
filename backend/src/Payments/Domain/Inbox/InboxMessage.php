<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Inbox;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Payments\Domain\Event\InboxMessageSettled;
use Eventor\Shared\Domain\Event\RecordsEvents;
use Eventor\Shared\Domain\Event\RecordsEventsInterface;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * A verified message from Pairrot (webhook or reconcile) waiting to be applied (chapter 06/07). The unique
 * `(provider, message_id)` is the deduplication of S03; the row is written by a plain `INSERT ... ON CONFLICT DO
 * NOTHING` (see InboxStore), this entity only closes it. It is global: the tenant is found while processing.
 */
#[ORM\Entity]
#[ORM\Table(name: 'inbox_message', schema: 'payments')]
class InboxMessage implements RecordsEventsInterface
{
    use RecordsEvents;

    public const string PENDING = 'pending';
    public const string PROCESSED = 'processed';
    public const string REJECTED = 'rejected';

    #[ORM\Column(type: 'string', length: 16)]
    private string $status = self::PENDING;

    #[ORM\Column(type: 'guid', nullable: true)]
    private ?string $organizerId = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $error = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    /**
     * @param array<string, mixed> $payload
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'guid')]
        private string $id,
        #[ORM\Column(type: 'string', length: 32)]
        private string $provider,
        #[ORM\Column(type: 'string', length: 190)]
        private string $messageId,
        #[ORM\Column(type: 'string', length: 100)]
        private string $type,
        #[ORM\Column(type: 'json', options: ['jsonb' => true])]
        private array $payload,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $receivedAt,
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function messageId(): string
    {
        return $this->messageId;
    }

    public function type(): string
    {
        return $this->type;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function error(): ?string
    {
        return $this->error;
    }

    public function isPending(): bool
    {
        return self::PENDING === $this->status;
    }

    public function organizerId(): ?OrganizerId
    {
        return null === $this->organizerId ? null : OrganizerId::fromString($this->organizerId);
    }

    public function processed(?OrganizerId $organizer, \DateTimeImmutable $now): void
    {
        $this->settle(self::PROCESSED, $organizer, null, $now);
    }

    public function rejected(?OrganizerId $organizer, string $reason, \DateTimeImmutable $now): void
    {
        $this->settle(self::REJECTED, $organizer, $reason, $now);
    }

    private function settle(string $status, ?OrganizerId $organizer, ?string $error, \DateTimeImmutable $now): void
    {
        $this->status = $status;
        $this->organizerId = $organizer?->toString();
        $this->error = null === $error ? null : mb_substr($error, 0, 255);
        $this->processedAt = $now;
        $this->record(new InboxMessageSettled($this->id, $this->type, $status, $this->error));
    }
}
