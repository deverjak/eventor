<?php

declare(strict_types=1);

namespace Eventor\Legal\Domain\Confirmation;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Legal\Contract\ConfirmationChannel;
use Eventor\Legal\Contract\ConsentDecision;
use Eventor\Legal\Contract\Exception\ConfirmationNotAllowed;
use Eventor\Legal\Domain\Event\ConfirmationAppended;
use Eventor\Legal\Domain\Requirement\LegalRequirement;
use Eventor\Shared\Domain\Event\RecordsEvents;
use Eventor\Shared\Domain\Event\RecordsEventsInterface;
use Eventor\Shared\Domain\Id\ConfirmationId;
use Eventor\Shared\Domain\Id\DocumentVersionId;
use Eventor\Shared\Domain\Id\LegalRequirementId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Id\PersonId;
use Eventor\Shared\Domain\Tenant\TenantScoped;

/**
 * One row of the append-only confirmation ledger (LEG-07, ADR 012). A later decision is a new row that points to the
 * row it supersedes; nothing is ever updated or deleted (the application role has no such grants). For a minor the
 * subject is the child and `decidedByPersonId` the guardian (LEG-11).
 */
#[ORM\Entity]
#[ORM\Table(name: 'confirmation', schema: 'legal')]
class Confirmation implements TenantScoped, RecordsEventsInterface
{
    use RecordsEvents;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'confirmation_id')]
        private ConfirmationId $id,
        #[ORM\Column(type: 'organizer_id')]
        private OrganizerId $organizerId,
        #[ORM\Column(type: 'legal_requirement_id')]
        private LegalRequirementId $requirementId,
        #[ORM\Column(name: 'document_version_id', type: 'document_version_id')]
        private DocumentVersionId $versionId,
        #[ORM\Column(type: 'person_id')]
        private PersonId $personId,
        #[ORM\Column(type: 'participation_id', nullable: true)]
        private ?ParticipationId $participationId,
        #[ORM\Column(type: 'order_id', nullable: true)]
        private ?OrderId $orderId,
        #[ORM\Column(type: 'person_id', nullable: true)]
        private ?PersonId $decidedByPersonId,
        #[ORM\Column(type: 'string', length: 16, enumType: ConsentDecision::class)]
        private ConsentDecision $decision,
        #[ORM\Column(type: 'string', length: 16, enumType: ConfirmationChannel::class)]
        private ConfirmationChannel $channel,
        #[ORM\Column(type: 'text', nullable: true)]
        private ?string $note,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $decidedAt,
        #[ORM\Column(type: 'confirmation_id', nullable: true)]
        private ?ConfirmationId $supersedesId,
    ) {}

    /**
     * @param ?ConsentDecision $previous the decision of the row this one supersedes (the latest of the chain)
     *
     * @throws ConfirmationNotAllowed
     */
    public static function append(
        ConfirmationId $id,
        LegalRequirement $requirement,
        DocumentVersionId $versionId,
        ConsentDecision $decision,
        PersonId $subject,
        ?PersonId $decidedBy,
        ?ParticipationId $participationId,
        ?OrderId $orderId,
        ConfirmationChannel $channel,
        ?string $note,
        \DateTimeImmutable $decidedAt,
        ?ConfirmationId $supersedes,
        ?ConsentDecision $previous,
    ): self {
        if (ConsentDecision::Declined === $decision && $requirement->isMandatory()) {
            throw ConfirmationNotAllowed::because('A mandatory requirement cannot be declined.');
        }
        if (ConsentDecision::Withdrawn === $decision) {
            if (!$requirement->isRevocable()) {
                throw ConfirmationNotAllowed::because('This confirmation is not revocable.');
            }
            if (ConsentDecision::Granted !== $previous) {
                throw ConfirmationNotAllowed::because('Only a granted confirmation can be withdrawn.');
            }
        }

        $row = new self($id, $requirement->organizerId(), $requirement->id(), $versionId, $subject, $participationId, $orderId, $decidedBy, $decision, $channel, $note, $decidedAt, $supersedes);
        $row->record(new ConfirmationAppended(
            $row->organizerId,
            $id,
            $requirement->id(),
            $requirement->type(),
            $versionId,
            $decision,
            $participationId,
            $orderId,
            $channel,
        ));

        return $row;
    }

    public function id(): ConfirmationId
    {
        return $this->id;
    }

    public function organizerId(): OrganizerId
    {
        return $this->organizerId;
    }

    public function decision(): ConsentDecision
    {
        return $this->decision;
    }
}
