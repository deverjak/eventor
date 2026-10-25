<?php

declare(strict_types=1);

namespace Eventor\Attention\Domain;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Attention\Contract\AttentionState;
use Eventor\Attention\Contract\Exception\AttentionActionNotAvailable;
use Eventor\Attention\Domain\Event\AttentionItemAcknowledged;
use Eventor\Attention\Domain\Event\AttentionItemClosed;
use Eventor\Attention\Domain\Event\AttentionItemOpened;
use Eventor\Shared\Application\Attention\AttentionType;
use Eventor\Shared\Domain\Event\RecordsEvents;
use Eventor\Shared\Domain\Event\RecordsEventsInterface;
use Eventor\Shared\Domain\Id\AttentionItemId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Something that needs a human (ADM-03): a dead-lettered message, a late payment, an unmatched transaction.
 * Lifecycle (state machine `attention_item`, declared in the module's services.php): open -> acknowledged ->
 * resolved | dismissed. At most one unresolved item exists per `dedupeKey` (partial unique index); once closed the
 * same cause may open a new item. The state setter is the workflow's marking store: call `acknowledge()` and
 * `close()` after the workflow applied the transition.
 *
 * Not `TenantScoped`: platform-wide items (no organizer) exist, so queries name the organizer explicitly.
 */
#[ORM\Entity]
#[ORM\Table(name: 'attention_item', schema: 'attention')]
#[ORM\Index(name: 'attention_item_open_idx', columns: ['organizer_id', 'state'])]
class AttentionItem implements RecordsEventsInterface
{
    use RecordsEvents;

    public const string RESOLUTION_ACTION = 'action';
    public const string RESOLUTION_AUTO = 'auto';

    /**
     * @param array<string, scalar|null> $payload
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'attention_item_id')]
        private AttentionItemId $id,
        #[ORM\Column(type: 'organizer_id', nullable: true)]
        private ?OrganizerId $organizerId,
        #[ORM\Column(type: 'string', length: 64, enumType: AttentionType::class)]
        private AttentionType $type,
        #[ORM\Column(type: 'string', length: 255)]
        private string $dedupeKey,
        #[ORM\Column(type: 'string', length: 64, nullable: true)]
        private ?string $subjectType,
        #[ORM\Column(type: 'string', length: 128, nullable: true)]
        private ?string $subjectId,
        #[ORM\Column(type: 'event_id', nullable: true)]
        private ?EventId $eventId,
        #[ORM\Column(type: 'json', options: ['jsonb' => true])]
        private array $payload,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $createdAt,
        #[ORM\Column(type: 'string', length: 16, enumType: AttentionState::class)]
        private AttentionState $state = AttentionState::Open,
        #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
        private ?\DateTimeImmutable $acknowledgedAt = null,
        #[ORM\Column(type: 'string', length: 128, nullable: true)]
        private ?string $acknowledgedBy = null,
        #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
        private ?\DateTimeImmutable $resolvedAt = null,
        #[ORM\Column(type: 'string', length: 128, nullable: true)]
        private ?string $resolvedBy = null,
        #[ORM\Column(type: 'string', length: 32, nullable: true)]
        private ?string $resolution = null,
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $resolutionNote = null,
    ) {}

    /**
     * @param array<string, scalar|null> $payload
     */
    public static function open(
        AttentionItemId $id,
        ?OrganizerId $organizerId,
        AttentionType $type,
        string $dedupeKey,
        ?string $subjectType,
        ?string $subjectId,
        ?EventId $eventId,
        array $payload,
        \DateTimeImmutable $now,
    ): self {
        $item = new self($id, $organizerId, $type, $dedupeKey, $subjectType, $subjectId, $eventId, $payload, $now);
        $item->record(new AttentionItemOpened($id, $organizerId, $type, $subjectType, $subjectId));

        return $item;
    }

    public function acknowledge(WorkflowInterface $machine, string $by, \DateTimeImmutable $now): void
    {
        $this->apply($machine, 'acknowledge');
        $this->acknowledgedAt = $now;
        $this->acknowledgedBy = $by;
        $this->record(new AttentionItemAcknowledged($this->id, $this->organizerId));
    }

    public function resolve(WorkflowInterface $machine, string $by, string $resolution, ?string $note, \DateTimeImmutable $now): void
    {
        $this->apply($machine, 'resolve');
        $this->close($by, $resolution, $note, $now);
    }

    public function dismiss(WorkflowInterface $machine, string $by, ?string $note, \DateTimeImmutable $now): void
    {
        $this->apply($machine, 'dismiss');
        $this->close($by, self::RESOLUTION_ACTION, $note, $now);
    }

    private function apply(WorkflowInterface $machine, string $transition): void
    {
        try {
            $machine->apply($this, $transition);
        } catch (NotEnabledTransitionException) {
            throw AttentionActionNotAvailable::for($transition, $this->state->value);
        }
    }

    private function close(string $by, string $resolution, ?string $note, \DateTimeImmutable $now): void
    {
        $this->resolvedAt = $now;
        $this->resolvedBy = $by;
        $this->resolution = $resolution;
        $this->resolutionNote = $note;
        $this->record(new AttentionItemClosed($this->id, $this->organizerId, $this->state, $resolution, $note));
    }

    public function id(): AttentionItemId
    {
        return $this->id;
    }

    public function organizerId(): ?OrganizerId
    {
        return $this->organizerId;
    }

    public function type(): AttentionType
    {
        return $this->type;
    }

    public function dedupeKey(): string
    {
        return $this->dedupeKey;
    }

    /** Marking store of the `attention_item` state machine. */
    public function getState(): AttentionState
    {
        return $this->state;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function setState(AttentionState $state, array $context = []): void
    {
        $this->state = $state;
    }
}
