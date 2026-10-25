<?php

declare(strict_types=1);

namespace Eventor\Legal\Domain\Requirement;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Legal\Contract\Confirmer;
use Eventor\Legal\Contract\RequirementType;
use Eventor\Legal\Domain\Event\ReconfirmationNeeded;
use Eventor\Legal\Domain\Event\RequirementDefined;
use Eventor\Shared\Domain\Event\RecordsEvents;
use Eventor\Shared\Domain\Event\RecordsEventsInterface;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\DocumentVersionId;
use Eventor\Shared\Domain\Id\LegalDocumentId;
use Eventor\Shared\Domain\Id\LegalRequirementId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Id\VariantId;
use Eventor\Shared\Domain\Tenant\TenantScoped;

/**
 * "This document has to be confirmed (or acknowledged) in this way" (LEG-04, LEG-05). Bound to the document, not to a
 * version: the version applied is the current published one at registration time. Archiving instead of deleting keeps
 * the ledger rows that reference it.
 */
#[ORM\Entity]
#[ORM\Table(name: 'legal_requirement', schema: 'legal')]
#[ORM\Index(name: 'legal_requirement_document_idx', columns: ['organizer_id', 'document_id'])]
class LegalRequirement implements TenantScoped, RecordsEventsInterface
{
    use RecordsEvents;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $archivedAt = null;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'legal_requirement_id')]
        private LegalRequirementId $id,
        #[ORM\Column(type: 'organizer_id')]
        private OrganizerId $organizerId,
        #[ORM\Column(type: 'legal_document_id')]
        private LegalDocumentId $documentId,
        #[ORM\Column(type: 'string', length: 24, enumType: RequirementType::class)]
        private RequirementType $type,
        #[ORM\Column(type: 'string', length: 500)]
        private string $purpose,
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $scopeText,
        #[ORM\Column(type: 'string', length: 200, nullable: true)]
        private ?string $validity,
        #[ORM\Column(type: 'string', length: 24, enumType: Confirmer::class)]
        private Confirmer $confirmer,
        #[ORM\Column(type: 'boolean')]
        private bool $revocable,
        #[ORM\Column(type: 'boolean')]
        private bool $mandatory,
        #[ORM\Column(type: 'activity_id', nullable: true)]
        private ?ActivityId $activityId,
        #[ORM\Column(type: 'variant_id', nullable: true)]
        private ?VariantId $variantId,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $createdAt,
    ) {}

    public static function define(
        LegalRequirementId $id,
        OrganizerId $organizerId,
        LegalDocumentId $documentId,
        RequirementType $type,
        string $purpose,
        ?string $scopeText,
        ?string $validity,
        Confirmer $confirmer,
        bool $revocable,
        bool $mandatory,
        bool $preChecked,
        ?ActivityId $activityId,
        ?VariantId $variantId,
        \DateTimeImmutable $now,
    ): self {
        RequirementPolicy::assertValid($type, $mandatory, $revocable, $preChecked);

        $requirement = new self($id, $organizerId, $documentId, $type, trim($purpose), $scopeText, $validity, $confirmer, $revocable, $mandatory, $activityId, $variantId, $now);
        $requirement->record(new RequirementDefined($organizerId, $id, 'defined', $requirement->snapshot()));

        return $requirement;
    }

    /**
     * Replaces the editable properties. The type is fixed: a different kind of requirement is a new requirement.
     */
    public function change(
        string $purpose,
        ?string $scopeText,
        ?string $validity,
        Confirmer $confirmer,
        bool $revocable,
        bool $mandatory,
        bool $preChecked,
        ?ActivityId $activityId,
        ?VariantId $variantId,
    ): void {
        RequirementPolicy::assertValid($this->type, $mandatory, $revocable, $preChecked);

        $this->purpose = trim($purpose);
        $this->scopeText = $scopeText;
        $this->validity = $validity;
        $this->confirmer = $confirmer;
        $this->revocable = $revocable;
        $this->mandatory = $mandatory;
        $this->activityId = $activityId;
        $this->variantId = $variantId;
        $this->record(new RequirementDefined($this->organizerId, $this->id, 'changed', $this->snapshot()));
    }

    public function archive(\DateTimeImmutable $now): void
    {
        if (null !== $this->archivedAt) {
            return;
        }

        $this->archivedAt = $now;
        $this->record(new RequirementDefined($this->organizerId, $this->id, 'archived', $this->snapshot()));
    }

    /**
     * @param list<ParticipationId> $participationIds
     * @param list<OrderId> $orderIds
     */
    public function requestReconfirmation(DocumentVersionId $version, array $participationIds, array $orderIds): void
    {
        $this->record(new ReconfirmationNeeded($this->organizerId, $this->id, $version, $participationIds, $orderIds));
    }

    public function id(): LegalRequirementId
    {
        return $this->id;
    }

    public function organizerId(): OrganizerId
    {
        return $this->organizerId;
    }

    public function documentId(): LegalDocumentId
    {
        return $this->documentId;
    }

    public function type(): RequirementType
    {
        return $this->type;
    }

    public function purpose(): string
    {
        return $this->purpose;
    }

    public function confirmer(): Confirmer
    {
        return $this->confirmer;
    }

    public function isRevocable(): bool
    {
        return $this->revocable;
    }

    public function isMandatory(): bool
    {
        return $this->mandatory;
    }

    public function isArchived(): bool
    {
        return null !== $this->archivedAt;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(): array
    {
        return [
            'documentId' => $this->documentId->toString(),
            'type' => $this->type->value,
            'confirmer' => $this->confirmer->value,
            'mandatory' => $this->mandatory,
            'revocable' => $this->revocable,
            'activityId' => $this->activityId?->toString(),
            'variantId' => $this->variantId?->toString(),
        ];
    }
}
