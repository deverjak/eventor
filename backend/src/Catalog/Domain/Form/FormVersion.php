<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Form;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Catalog\Domain\Event\FormDraftChanged;
use Eventor\Catalog\Domain\Event\FormVersionFrozen;
use Eventor\Catalog\Domain\Exception\FormVersionImmutable;
use Eventor\Shared\Domain\Event\RecordsEvents;
use Eventor\Shared\Domain\Event\RecordsEventsInterface;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\FormVersionId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\UserAccountId;
use Eventor\Shared\Domain\Tenant\TenantScoped;

/**
 * One version of the registration form of an event (ADR 007, FRM-08). A version without `publishedAt` is the draft:
 * the only mutable one (at most one per event). Publishing freezes it with a hash of the canonical schema; editing a
 * published form starts a draft of the next number, so answers always carry the version they were given in.
 */
#[ORM\Entity]
#[ORM\Table(name: 'form_version', schema: 'catalog')]
#[ORM\UniqueConstraint(name: 'form_version_event_number_key', columns: ['event_id', 'number'])]
class FormVersion implements TenantScoped, RecordsEventsInterface
{
    use RecordsEvents;

    /** @var array<array-key, mixed> */
    #[ORM\Column(name: 'schema', type: 'json', options: ['jsonb' => true])]
    private array $schema;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $schemaSha256 = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $updatedAt;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'form_version_id')]
        private FormVersionId $id,
        #[ORM\Column(type: 'organizer_id')]
        private OrganizerId $organizerId,
        #[ORM\Column(type: 'event_id')]
        private EventId $eventId,
        #[ORM\Column(type: 'integer')]
        private int $number,
        FormSchema $schema,
        #[ORM\Column(type: 'user_account_id', nullable: true)]
        private ?UserAccountId $createdBy,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $createdAt,
    ) {
        $this->schema = $schema->toArray();
        $this->updatedAt = $createdAt;
    }

    public static function startDraft(FormVersionId $id, OrganizerId $organizerId, EventId $eventId, int $number, FormSchema $schema, ?UserAccountId $createdBy, \DateTimeImmutable $now): self
    {
        $draft = new self($id, $organizerId, $eventId, $number, $schema, $createdBy, $now);
        $draft->record(new FormDraftChanged($organizerId, $eventId, $id, $number, \count($schema->fields)));

        return $draft;
    }

    public function id(): FormVersionId
    {
        return $this->id;
    }

    public function eventId(): EventId
    {
        return $this->eventId;
    }

    public function organizerId(): OrganizerId
    {
        return $this->organizerId;
    }

    public function number(): int
    {
        return $this->number;
    }

    public function isPublished(): bool
    {
        return null !== $this->publishedAt;
    }

    public function publishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function schemaSha256(): ?string
    {
        return $this->schemaSha256;
    }

    public function createdBy(): ?UserAccountId
    {
        return $this->createdBy;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function schema(): FormSchema
    {
        return FormSchema::fromArray($this->schema);
    }

    /**
     * @throws FormVersionImmutable
     */
    public function replaceSchema(FormSchema $schema, \DateTimeImmutable $now): void
    {
        if ($this->isPublished()) {
            throw FormVersionImmutable::create($this->number);
        }
        $this->schema = $schema->toArray();
        $this->updatedAt = $now;
        $this->record(new FormDraftChanged($this->organizerId, $this->eventId, $this->id, $this->number, \count($schema->fields)));
    }

    /**
     * Freezes the draft. `previous` is the schema of the version published before it (null for the first one); every
     * field that is required now but was not required there is reported as added (FRM-09, S17).
     *
     * @throws FormVersionImmutable
     */
    public function publish(?FormVersion $previous, \DateTimeImmutable $now): void
    {
        if ($this->isPublished()) {
            throw FormVersionImmutable::create($this->number);
        }
        $schema = $this->schema();
        $before = null === $previous ? [] : $previous->schema()->requiredKeys();
        $added = array_values(array_diff($schema->requiredKeys(), $before));

        $this->schemaSha256 = $schema->sha256();
        $this->publishedAt = $now;
        $this->updatedAt = $now;
        $this->record(new FormVersionFrozen($this->organizerId, $this->eventId, $this->id, $this->number, $previous?->id(), null === $previous ? [] : $added, $this->schemaSha256));
    }
}
