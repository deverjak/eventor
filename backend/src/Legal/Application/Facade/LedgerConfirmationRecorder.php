<?php

declare(strict_types=1);

namespace Eventor\Legal\Application\Facade;

use Eventor\Legal\Contract\ConfirmationRecorder;
use Eventor\Legal\Contract\ConfirmationToRecord;
use Eventor\Legal\Contract\ConsentDecision;
use Eventor\Legal\Contract\Exception\ConfirmationNotAllowed;
use Eventor\Legal\Domain\Confirmation\Confirmation;
use Eventor\Legal\Domain\Confirmation\ConfirmationRepository;
use Eventor\Legal\Domain\Document\LegalDocumentRepository;
use Eventor\Legal\Domain\Requirement\LegalRequirementRepository;
use Eventor\Registration\Contract\Exception\ParticipationNotFound;
use Eventor\Registration\Contract\ParticipationQuery;
use Eventor\Shared\Domain\Id\ConfirmationId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Appends decisions to the confirmation ledger inside the transaction of the caller (LEG-07). It checks that the
 * requirement belongs to the organizer, that the version is a published version of the requirement's document and
 * that the decision is allowed, then chains the new row to the latest one of (requirement, subject, participation or
 * order). Domain events recorded by the rows are dispatched by the caller's command bus (`legal.confirmation_recorded`,
 * `legal.consent_withdrawn`).
 */
#[AsAlias(ConfirmationRecorder::class)]
final readonly class LedgerConfirmationRecorder implements ConfirmationRecorder
{
    public function __construct(
        private LegalRequirementRepository $requirements,
        private LegalDocumentRepository $documents,
        private ConfirmationRepository $ledger,
        private ?ParticipationQuery $participations = null,
    ) {}

    public function record(OrganizerId $organizer, array $confirmations): array
    {
        $ids = [];
        foreach ($confirmations as $toRecord) {
            $requirement = $this->requirements->find($toRecord->requirementId);
            if (null === $requirement || !$requirement->organizerId()->equals($organizer)) {
                throw ConfirmationNotAllowed::because('The requirement does not exist.');
            }
            $version = $this->documents->findVersion($toRecord->versionId);
            if (null === $version || !$version->isPublished() || !$version->documentId()->equals($requirement->documentId())) {
                throw ConfirmationNotAllowed::because('The version is not a published version of the document of this requirement.');
            }

            $previous = $this->ledger->latestOfChain($requirement->id(), $toRecord->subject, $toRecord->participationId, $toRecord->orderId);
            $orderId = $toRecord->orderId;
            if (ConsentDecision::Withdrawn === $toRecord->decision && null === $orderId) {
                // ConsentWithdrawn names the order; the withdrawing caller may know the participation only.
                $orderId = $previous['orderId'] ?? $this->orderOf($organizer, $toRecord);
            }

            $row = Confirmation::append(
                ConfirmationId::generate(),
                $requirement,
                $toRecord->versionId,
                $toRecord->decision,
                $toRecord->subject,
                $toRecord->decidedBy,
                $toRecord->participationId,
                $orderId,
                $toRecord->channel,
                $toRecord->note,
                $toRecord->decidedAt,
                $previous['id'] ?? null,
                $previous['decision'] ?? null,
            );
            $this->ledger->append($row);
            $ids[] = $row->id();
        }

        return $ids;
    }

    private function orderOf(OrganizerId $organizer, ConfirmationToRecord $toRecord): OrderId
    {
        if (null !== $this->participations && null !== $toRecord->participationId) {
            try {
                return $this->participations->find($organizer, $toRecord->participationId)->orderId;
            } catch (ParticipationNotFound) {
            }
        }

        throw ConfirmationNotAllowed::because('The order of the withdrawn confirmation could not be determined.');
    }
}
