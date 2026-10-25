<?php

declare(strict_types=1);

namespace Eventor\Legal\Application\Facade;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Eventor\Legal\Application\Query\Cells;
use Eventor\Legal\Contract\ApplicableRequirement;
use Eventor\Legal\Contract\ApplicableRequirements;
use Eventor\Legal\Contract\Confirmer;
use Eventor\Legal\Contract\ConsentDecision;
use Eventor\Legal\Contract\Exception\RequirementNotSatisfied;
use Eventor\Legal\Contract\RegistrationSlot;
use Eventor\Legal\Contract\RequirementResolver;
use Eventor\Legal\Contract\RequirementType;
use Eventor\Shared\Domain\Id\DocumentVersionId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\LegalDocumentId;
use Eventor\Shared\Domain\Id\LegalRequirementId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Which requirements apply (ADR 012): organizer -> event -> activity documents, minus the documents that a lower-level
 * document replaces, restricted by the activity/variant of the requirement, split into the order level (confirmed
 * once by the orderer) and the slot level (each participant, or the guardian for a minor). Only requirements of
 * documents with a published version apply, with that current version.
 */
#[AsAlias(RequirementResolver::class)]
final readonly class DbalRequirementResolver implements RequirementResolver
{
    private const string NIL_UUID = '00000000-0000-0000-0000-000000000000';

    public function __construct(private Connection $connection) {}

    public function forRegistration(OrganizerId $organizer, EventId $event, array $slots): ApplicableRequirements
    {
        $activities = array_values(array_unique(array_map(static fn(RegistrationSlot $s): string => $s->activityId->toString(), $slots)));

        /** @var list<array{requirement_id: string, document_id: string, version_id: string, type: string, purpose: string, confirmer: string, mandatory: bool, revocable: bool, activity_id: ?string, variant_id: ?string, level: string, scope_id: string, title: string, replaces: ?string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            "SELECT r.id AS requirement_id, d.id AS document_id, d.current_version_id AS version_id, r.type, r.purpose, r.confirmer,
                    r.mandatory, r.revocable, r.activity_id, r.variant_id, d.level, d.scope_id, d.title, d.replaces_document_id AS replaces
               FROM legal.legal_requirement r
               JOIN legal.legal_document d ON d.id = r.document_id
              WHERE r.organizer_id = :organizer
                AND r.archived_at IS NULL
                AND d.current_version_id IS NOT NULL
                AND ((d.level = 'organizer' AND d.scope_id = :organizer)
                  OR (d.level = 'event' AND d.scope_id = :event)
                  OR (d.level = 'activity' AND d.scope_id IN (:activities)))
              ORDER BY CASE d.level WHEN 'organizer' THEN 0 WHEN 'event' THEN 1 ELSE 2 END, r.created_at, r.id",
            ['organizer' => $organizer->toString(), 'event' => $event->toString(), 'activities' => [] === $activities ? [self::NIL_UUID] : $activities],
            ['activities' => ArrayParameterType::STRING],
        );

        $orderLevel = [];
        $perSlot = [];
        foreach ($slots as $slot) {
            $perSlot[$slot->slotRef] = [];
            foreach ($this->effectiveFor($rows, $organizer, $event, $slot) as $row) {
                $requirement = $this->toApplicable($row);
                if (Confirmer::Orderer === $requirement->confirmer) {
                    $orderLevel[Cells::string($row['requirement_id'])] = $requirement;
                } elseif (Confirmer::Guardian !== $requirement->confirmer || $slot->minor) {
                    $perSlot[$slot->slotRef][] = $requirement;
                }
            }
        }
        if ([] === $slots) {
            foreach ($this->effectiveFor($rows, $organizer, $event, null) as $row) {
                if (Confirmer::Orderer === Confirmer::from(Cells::string($row['confirmer']))) {
                    $orderLevel[Cells::string($row['requirement_id'])] = $this->toApplicable($row);
                }
            }
        }

        return new ApplicableRequirements(array_values($orderLevel), $perSlot);
    }

    public function assertSatisfied(ApplicableRequirements $requirements, array $decisions): void
    {
        $granted = [];
        foreach ($decisions as $decision) {
            if (ConsentDecision::Granted === $decision->decision) {
                $granted[$this->key($decision->slotRef, $decision->requirementId, $decision->versionId)] = true;
            }
        }

        $missing = [];
        foreach ($requirements->orderLevel as $requirement) {
            if ($requirement->mandatory && !isset($granted[$this->key(null, $requirement->requirementId, $requirement->versionId)])) {
                $missing[$requirement->requirementId->toString()] = $requirement->requirementId;
            }
        }
        foreach ($requirements->perSlot as $slotRef => $slotRequirements) {
            foreach ($slotRequirements as $requirement) {
                if ($requirement->mandatory && !isset($granted[$this->key((string) $slotRef, $requirement->requirementId, $requirement->versionId)])) {
                    $missing[$requirement->requirementId->toString()] = $requirement->requirementId;
                }
            }
        }

        if ([] !== $missing) {
            throw new RequirementNotSatisfied(array_values($missing));
        }
    }

    /**
     * Requirements in force for one slot: documents of the organizer, the event and the slot's activity, without those
     * replaced by one of them, restricted to the slot's activity and variant.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function effectiveFor(array $rows, OrganizerId $organizer, EventId $event, ?RegistrationSlot $slot): array
    {
        $inScope = array_values(array_filter($rows, static fn(array $row): bool => match ($row['level']) {
            'organizer' => $row['scope_id'] === $organizer->toString(),
            'event' => $row['scope_id'] === $event->toString(),
            default => null !== $slot && $row['scope_id'] === $slot->activityId->toString(),
        }));

        $replaced = [];
        foreach ($inScope as $row) {
            if (\is_string($row['replaces'])) {
                $replaced[$row['replaces']] = true;
            }
        }

        return array_values(array_filter($inScope, static function (array $row) use ($replaced, $slot): bool {
            if (isset($replaced[Cells::string($row['document_id'])])) {
                return false;
            }
            if (null === $slot) {
                return true;
            }

            return (null === $row['activity_id'] || $row['activity_id'] === $slot->activityId->toString())
                && (null === $row['variant_id'] || $row['variant_id'] === $slot->variantId->toString());
        }));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function toApplicable(array $row): ApplicableRequirement
    {
        return new ApplicableRequirement(
            LegalRequirementId::fromString(Cells::string($row['requirement_id'])),
            LegalDocumentId::fromString(Cells::string($row['document_id'])),
            DocumentVersionId::fromString(Cells::string($row['version_id'])),
            RequirementType::from(Cells::string($row['type'])),
            Cells::string($row['purpose']),
            Confirmer::from(Cells::string($row['confirmer'])),
            (bool) $row['mandatory'],
            (bool) $row['revocable'],
            Cells::string($row['title']),
        );
    }

    private function key(?string $slotRef, LegalRequirementId $requirement, DocumentVersionId $version): string
    {
        return ($slotRef ?? '').'|'.$requirement->toString().'|'.$version->toString();
    }
}
