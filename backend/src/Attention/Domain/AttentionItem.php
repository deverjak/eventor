<?php

declare(strict_types=1);

namespace Eventor\Attention\Domain;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Something that needs a human (ADM-03): a dead-lettered message, a late payment, an unmatched transaction.
 * Phase F0 maps the table; the lifecycle (assign, resolve, actions per type) arrives with the Attention module
 * work of phase F2. Items are deduplicated by `dedupeKey`.
 */
#[ORM\Entity]
#[ORM\Table(name: 'attention_item', schema: 'attention')]
#[ORM\UniqueConstraint(name: 'attention_item_dedupe_key_key', columns: ['dedupe_key'])]
#[ORM\Index(name: 'attention_item_open_idx', columns: ['organizer_id', 'state'])]
class AttentionItem
{
    public const string STATE_OPEN = 'OPEN';
    public const string STATE_ASSIGNED = 'ASSIGNED';
    public const string STATE_RESOLVED = 'RESOLVED';

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'guid')]
        private string $id,
        #[ORM\Column(type: 'organizer_id', nullable: true)]
        private ?OrganizerId $organizerId,
        #[ORM\Column(type: 'string', length: 64)]
        private string $type,
        #[ORM\Column(type: 'string', length: 255)]
        private string $dedupeKey,
        #[ORM\Column(type: 'string', length: 64, nullable: true)]
        private ?string $subjectType,
        #[ORM\Column(type: 'string', length: 128, nullable: true)]
        private ?string $subjectId,
        #[ORM\Column(type: 'json', options: ['jsonb' => true])]
        private array $payload,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $createdAt,
        #[ORM\Column(type: 'string', length: 16)]
        private string $state = self::STATE_OPEN,
        #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
        private ?\DateTimeImmutable $resolvedAt = null,
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function state(): string
    {
        return $this->state;
    }

    public function dedupeKey(): string
    {
        return $this->dedupeKey;
    }

    public function organizerId(): ?OrganizerId
    {
        return $this->organizerId;
    }
}
