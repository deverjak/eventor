<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Query;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Eventor\Legal\Contract\ConsentQuery;
use Eventor\Legal\Contract\MissingConfirmation;
use Eventor\Payments\Contract\FinanceQuery;
use Eventor\Registration\Contract\AnswersState;
use Eventor\Registration\Contract\Exception\ParticipationNotFound;
use Eventor\Registration\Contract\LegalState;
use Eventor\Registration\Contract\MissingConfirmationView;
use Eventor\Registration\Contract\OtherParticipationView;
use Eventor\Registration\Contract\ParticipationCheckInView;
use Eventor\Registration\Contract\ParticipationQuery;
use Eventor\Registration\Contract\ParticipationSearchHit;
use Eventor\Registration\Contract\ParticipationState;
use Eventor\Registration\Contract\ParticipationView;
use Eventor\Shared\Domain\Currency;
use Eventor\Shared\Domain\Finance\FinancialState;
use Eventor\Shared\Domain\Finance\FinancialStateView;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Id\PersonId;
use Eventor\Shared\Domain\Id\VariantId;
use Eventor\Shared\Domain\Id\WaveId;
use Eventor\Shared\Domain\Money;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The facts of participations for other modules (Legal, CheckIn, Communication): ids, states, no personal data, except the
 * check-in projection which carries the display name the desk must show (CHK-04).
 */
#[AsAlias(ParticipationQuery::class)]
final readonly class ParticipationFactsQuery implements ParticipationQuery
{
    private const string COLUMNS = 'p.id, p.order_id, p.person_id, p.event_id, p.activity_id, p.variant_id, p.wave_id, p.state, p.legal_state, p.answers_state, p.hold_expires_at, p.registration_code, p.category_code';

    public function __construct(
        private Connection $connection,
        private OfferLabels $labels,
        private FinanceQuery $finance,
        #[Autowire(service: ConsentQuery::class, lazy: true)]
        private ConsentQuery $consents,
    ) {}

    public function find(OrganizerId $organizer, ParticipationId $id): ParticipationView
    {
        return $this->findMany($organizer, [$id])[0] ?? throw ParticipationNotFound::forId($id);
    }

    public function findMany(OrganizerId $organizer, array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT '.self::COLUMNS.' FROM registration.participation p WHERE p.organizer_id = :organizer AND p.id IN (:ids) ORDER BY p.created_at, p.id',
            ['organizer' => $organizer->toString(), 'ids' => array_map(static fn(ParticipationId $id): string => $id->toString(), $ids)],
            ['ids' => ArrayParameterType::STRING],
        );

        return array_map(self::view(...), $rows);
    }

    public function forCheckIn(OrganizerId $organizer, ParticipationId $id): ParticipationCheckInView
    {
        /** @var array<string, mixed>|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT '.self::COLUMNS.', pe.first_name, pe.last_name, o.amount_due_minor, o.amount_due_currency
               FROM registration.participation p
               JOIN registration.person pe ON pe.id = p.person_id
               JOIN registration.orders o ON o.id = p.order_id
              WHERE p.organizer_id = :organizer AND p.id = :id',
            ['organizer' => $organizer->toString(), 'id' => $id->toString()],
        );
        if (false === $row) {
            throw ParticipationNotFound::forId($id);
        }

        $view = self::view($row);
        $name = trim(Cells::string($row['first_name'] ?? '').' '.Cells::string($row['last_name'] ?? ''));
        $currency = Currency::from(Cells::string($row['amount_due_currency']));
        $finance = $this->finance->summaries($organizer, [$view->orderId])[$view->orderId->toString()]
            ?? new FinancialStateView(FinancialState::Unpaid, Money::of(Cells::int($row['amount_due_minor']), $currency), Money::zero($currency));

        $missing = $this->consents->missingFor($organizer, [$id])[$id->toString()] ?? [];

        return new ParticipationCheckInView(
            $view->id,
            $view->orderId,
            $view->personId,
            '' === $name ? '-' : $name,
            $view->registrationCode,
            $view->activityId,
            $this->labels->activityName($organizer, $view->eventId, $view->activityId),
            $this->labels->variantName($organizer, $view->eventId, $view->variantId),
            null,
            $view->categoryCode,
            $view->state,
            $view->legalState,
            $view->answersState,
            $finance,
            array_map(static fn(MissingConfirmation $m): MissingConfirmationView => new MissingConfirmationView($m->requirementId, $m->documentTitle, $m->mandatory), $missing),
            null,
            $this->others($organizer, $view),
        );
    }

    public function findByRegistrationCode(OrganizerId $organizer, string $registrationCode): ?ParticipationId
    {
        $id = $this->connection->fetchOne(
            'SELECT id FROM registration.participation WHERE organizer_id = :organizer AND registration_code = :code',
            ['organizer' => $organizer->toString(), 'code' => strtoupper(trim($registrationCode))],
        );

        return false === $id ? null : ParticipationId::fromString(Cells::string($id));
    }

    public function searchForCheckIn(OrganizerId $organizer, ActivityId $activity, string $term, int $limit = 20): array
    {
        $needle = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower(trim($term))).'%';

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            "SELECT p.id, p.registration_code, p.activity_id, p.state, pe.first_name, pe.last_name
               FROM registration.participation p
               JOIN registration.person pe ON pe.id = p.person_id
              WHERE p.organizer_id = :organizer AND p.activity_id = :activity
                AND (lower(coalesce(pe.first_name, '') || ' ' || coalesce(pe.last_name, '')) LIKE :needle
                  OR lower(coalesce(pe.last_name, '') || ' ' || coalesce(pe.first_name, '')) LIKE :needle
                  OR lower(p.registration_code) LIKE :needle)
              ORDER BY pe.last_name, pe.first_name, p.id LIMIT :limit",
            ['organizer' => $organizer->toString(), 'activity' => $activity->toString(), 'needle' => $needle, 'limit' => max(1, $limit)],
        );

        return array_map(static fn(array $row): ParticipationSearchHit => new ParticipationSearchHit(
            ParticipationId::fromString(Cells::string($row['id'])),
            trim(Cells::string($row['first_name'] ?? '').' '.Cells::string($row['last_name'] ?? '')),
            Cells::string($row['registration_code']),
            ActivityId::fromString(Cells::string($row['activity_id'])),
            ParticipationState::from(Cells::string($row['state'])),
        ), $rows);
    }

    /**
     * @return list<OtherParticipationView>
     */
    private function others(OrganizerId $organizer, ParticipationView $of): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, activity_id, state, event_id FROM registration.participation WHERE organizer_id = :organizer AND person_id = :person AND id <> :id ORDER BY created_at, id',
            ['organizer' => $organizer->toString(), 'person' => $of->personId->toString(), 'id' => $of->id->toString()],
        );

        return array_map(function (array $row) use ($organizer): OtherParticipationView {
            $activity = ActivityId::fromString(Cells::string($row['activity_id']));
            $event = EventId::fromString(Cells::string($row['event_id']));

            return new OtherParticipationView(
                ParticipationId::fromString(Cells::string($row['id'])),
                $activity,
                $this->labels->activityName($organizer, $event, $activity),
                $this->labels->activityStart($organizer, $event, $activity),
                ParticipationState::from(Cells::string($row['state'])),
            );
        }, $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function view(array $row): ParticipationView
    {
        return new ParticipationView(
            ParticipationId::fromString(Cells::string($row['id'])),
            OrderId::fromString(Cells::string($row['order_id'])),
            PersonId::fromString(Cells::string($row['person_id'])),
            EventId::fromString(Cells::string($row['event_id'])),
            ActivityId::fromString(Cells::string($row['activity_id'])),
            VariantId::fromString(Cells::string($row['variant_id'])),
            null === $row['wave_id'] ? null : WaveId::fromString(Cells::string($row['wave_id'])),
            ParticipationState::from(Cells::string($row['state'])),
            LegalState::from(Cells::string($row['legal_state'])),
            AnswersState::from(Cells::string($row['answers_state'])),
            Cells::nullableTime($row['hold_expires_at']),
            Cells::string($row['registration_code']),
            Cells::nullableString($row['category_code']),
        );
    }
}
