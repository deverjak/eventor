<?php

declare(strict_types=1);

namespace Eventor\Legal\Application\Facade;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Eventor\Legal\Contract\ApplicableRequirement;
use Eventor\Legal\Contract\ConsentDecision;
use Eventor\Legal\Contract\ConsentQuery;
use Eventor\Legal\Contract\MissingConfirmation;
use Eventor\Legal\Contract\RegistrationSlot;
use Eventor\Legal\Contract\RequirementResolver;
use Eventor\Legal\Contract\RequirementType;
use Eventor\Registration\Contract\ParticipationQuery;
use Eventor\Registration\Contract\ParticipationView;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Id\PersonId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Current state of confirmations: the latest (not superseded) row of every chain. A granted confirmation of an
 * older version stays valid unless a later version was published with "requires reconfirmation" (S14 / S15).
 * Participation facts (event, activity, variant, order) come from Registration's `ParticipationQuery`.
 */
#[AsAlias(ConsentQuery::class)]
final readonly class DbalConsentQuery implements ConsentQuery
{
    private const string LATEST = 'NOT EXISTS (SELECT 1 FROM legal.confirmation n WHERE n.supersedes_id = c.id)';

    public function __construct(
        private Connection $connection,
        private RequirementResolver $resolver,
        private ?ParticipationQuery $participations = null,
    ) {}

    public function missingFor(OrganizerId $organizer, array $participationIds): array
    {
        if ([] === $participationIds) {
            return [];
        }
        $views = $this->participationQuery()->findMany($organizer, $participationIds);
        if ([] === $views) {
            return [];
        }

        $targets = [];
        foreach ($views as $view) {
            $targets[] = $view->id->toString();
            $targets[] = $view->orderId->toString();
        }
        $minors = $this->minors($organizer, array_map(static fn(ParticipationView $v): string => $v->id->toString(), $views));
        $latest = $this->latestDecisions($organizer, array_values(array_unique($targets)));

        $missing = [];
        foreach ($views as $view) {
            $slot = new RegistrationSlot($view->id->toString(), $view->activityId, $view->variantId, isset($minors[$view->id->toString()]));
            $applicable = $this->resolver->forRegistration($organizer, $view->eventId, [$slot]);

            $owed = [];
            foreach ($applicable->orderLevel as $requirement) {
                $owed = $this->evaluate($owed, $requirement, $latest[$requirement->requirementId->toString().'|'.$view->orderId->toString()] ?? null);
            }
            foreach ($applicable->perSlot[$slot->slotRef] ?? [] as $requirement) {
                $owed = $this->evaluate($owed, $requirement, $latest[$requirement->requirementId->toString().'|'.$view->id->toString()] ?? null);
            }
            if ([] !== $owed) {
                $missing[$view->id->toString()] = $owed;
            }
        }

        return $missing;
    }

    public function grantedAmong(OrganizerId $organizer, RequirementType $type, string $purpose, array $persons): array
    {
        if ([] === $persons) {
            return [];
        }

        // The latest decision of a person among the requirements of this type and purpose decides: a later withdrawal wins.
        /** @var list<string> $granted */
        $granted = $this->connection->fetchFirstColumn(
            'SELECT person_id FROM (
                SELECT DISTINCT ON (c.person_id) c.person_id, c.decision
                  FROM legal.confirmation c
                  JOIN legal.legal_requirement r ON r.id = c.requirement_id
                 WHERE c.organizer_id = :organizer AND r.type = :type AND r.purpose = :purpose AND c.person_id IN (:persons)
                 ORDER BY c.person_id, c.decided_at DESC, c.id DESC
             ) latest
             WHERE decision = :granted',
            [
                'organizer' => $organizer->toString(),
                'type' => $type->value,
                'purpose' => $purpose,
                'persons' => array_map(static fn(PersonId $p): string => $p->toString(), $persons),
                'granted' => ConsentDecision::Granted->value,
            ],
            ['persons' => ArrayParameterType::STRING],
        );

        return array_values(array_map(PersonId::fromString(...), $granted));
    }

    public function personsWithGrantedConsent(OrganizerId $organizer, EventId $event, RequirementType $type): array
    {
        /** @var list<array{person_id: string, participation_id: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT DISTINCT c.person_id, c.participation_id
               FROM legal.confirmation c
               JOIN legal.legal_requirement r ON r.id = c.requirement_id
              WHERE c.organizer_id = :organizer AND r.type = :type AND c.participation_id IS NOT NULL
                AND c.decision = :granted AND '.self::LATEST,
            ['organizer' => $organizer->toString(), 'type' => $type->value, 'granted' => ConsentDecision::Granted->value],
        );
        if ([] === $rows) {
            return [];
        }

        $persons = [];
        foreach (array_chunk($rows, 500) as $chunk) {
            $byParticipation = [];
            foreach ($chunk as $row) {
                $byParticipation[$row['participation_id']] = $row['person_id'];
            }
            $views = $this->participationQuery()->findMany($organizer, array_map(ParticipationId::fromString(...), array_keys($byParticipation)));
            foreach ($views as $view) {
                if ($view->eventId->equals($event)) {
                    $persons[$byParticipation[$view->id->toString()]] = PersonId::fromString($byParticipation[$view->id->toString()]);
                }
            }
        }

        return array_values($persons);
    }

    /**
     * @param list<MissingConfirmation> $owed
     * @param array{decision: string, version: string, stale: bool}|null $latest
     *
     * @return list<MissingConfirmation>
     */
    private function evaluate(array $owed, ApplicableRequirement $requirement, ?array $latest): array
    {
        $granted = null !== $latest && ConsentDecision::Granted->value === $latest['decision'];
        if ($granted && !$latest['stale']) {
            return $owed;
        }
        if ($granted || $requirement->mandatory) {
            $owed[] = new MissingConfirmation($requirement->requirementId, $requirement->type, $requirement->documentTitle, $requirement->mandatory, $granted);
        }

        return $owed;
    }

    /**
     * A participation is a minor's when a guardian decided for it before (the slot of the resolver then asks the guardian).
     *
     * @param list<string> $participationIds
     *
     * @return array<string, true>
     */
    private function minors(OrganizerId $organizer, array $participationIds): array
    {
        /** @var list<string> $ids */
        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT participation_id FROM legal.confirmation WHERE organizer_id = :organizer AND participation_id IN (:ids) AND decided_by_person_id IS NOT NULL',
            ['organizer' => $organizer->toString(), 'ids' => $participationIds],
            ['ids' => ArrayParameterType::STRING],
        );

        return array_fill_keys($ids, true);
    }

    /**
     * @param list<string> $targets participation and order ids
     *
     * @return array<string, array{decision: string, version: string, stale: bool}> keyed by "requirement|target"
     */
    private function latestDecisions(OrganizerId $organizer, array $targets): array
    {
        /** @var list<array{requirement_id: string, target: string, decision: string, version: string, stale: bool}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT c.requirement_id, COALESCE(c.participation_id, c.order_id) AS target, c.decision, c.document_version_id AS version,
                    EXISTS (SELECT 1 FROM legal.document_version gv
                              JOIN legal.document_version nv ON nv.document_id = gv.document_id AND nv.number > gv.number
                             WHERE gv.id = c.document_version_id AND nv.published_at IS NOT NULL AND nv.requires_reconfirmation) AS stale
               FROM legal.confirmation c
              WHERE c.organizer_id = :organizer AND COALESCE(c.participation_id, c.order_id) IN (:targets) AND '.self::LATEST.'
              ORDER BY c.decided_at, c.id',
            ['organizer' => $organizer->toString(), 'targets' => $targets],
            ['targets' => ArrayParameterType::STRING],
        );

        $latest = [];
        foreach ($rows as $row) {
            $latest[$row['requirement_id'].'|'.$row['target']] = ['decision' => $row['decision'], 'version' => $row['version'], 'stale' => (bool) $row['stale']];
        }

        return $latest;
    }

    private function participationQuery(): ParticipationQuery
    {
        return $this->participations ?? throw new \LogicException('Registration does not provide ParticipationQuery yet.');
    }
}
