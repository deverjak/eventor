<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Payments\Contract\FinanceQuery;
use Eventor\Registration\Application\Query\View\AdminOrderView;
use Eventor\Registration\Application\Query\View\ParticipationListView;
use Eventor\Registration\Application\Query\View\ParticipationRowView;
use Eventor\Registration\Contract\AnswersState;
use Eventor\Registration\Contract\Exception\OrderNotFound;
use Eventor\Registration\Contract\Exception\ParticipationNotFound;
use Eventor\Registration\Contract\LegalState;
use Eventor\Registration\Contract\ParticipationState;
use Eventor\Shared\Domain\Currency;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Id\PersonId;
use Eventor\Shared\Domain\Id\VariantId;
use Eventor\Shared\Domain\Money;
use Eventor\Shared\Domain\Payment\PaymentMethod;

/**
 * Participant list, participation and order detail for the organizer's team (ADM-01 view). DBAL, explicit organizer.
 */
final readonly class AdminParticipationQuery
{
    private const string COLUMNS = 'p.id, p.order_id, o.number, p.person_id, pe.first_name, pe.last_name, pe.email, p.event_id, p.registration_code, p.activity_id, p.variant_id,
        p.state, p.legal_state, p.answers_state, p.hold_expires_at, p.price_minor, p.price_currency, p.created_at';
    private const string FROM = 'FROM registration.participation p
        JOIN registration.orders o ON o.id = p.order_id
        JOIN registration.person pe ON pe.id = p.person_id';

    public const int MAX_LIMIT = 200;

    public function __construct(
        private Connection $connection,
        private OfferLabels $labels,
        private FinanceQuery $finance,
    ) {}

    public function list(OrganizerId $organizer, EventId $event, ?ParticipationState $state, ?ActivityId $activity, ?string $term, int $page, int $limit): ParticipationListView
    {
        $limit = max(1, min(self::MAX_LIMIT, $limit));
        $page = max(1, $page);
        $where = 'p.organizer_id = :organizer AND p.event_id = :event';
        $params = ['organizer' => $organizer->toString(), 'event' => $event->toString()];
        if (null !== $state) {
            $where .= ' AND p.state = :state';
            $params['state'] = $state->value;
        }
        if (null !== $activity) {
            $where .= ' AND p.activity_id = :activity';
            $params['activity'] = $activity->toString();
        }
        if (null !== $term && '' !== trim($term)) {
            $where .= " AND (lower(coalesce(pe.first_name, '') || ' ' || coalesce(pe.last_name, '')) LIKE :term OR lower(pe.email) LIKE :term OR lower(p.registration_code) LIKE :term OR lower(o.number) LIKE :term)";
            $params['term'] = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower(trim($term))).'%';
        }

        $total = Cells::int($this->connection->fetchOne('SELECT count(*) '.self::FROM.' WHERE '.$where, $params));
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT '.self::COLUMNS.' '.self::FROM.' WHERE '.$where.' ORDER BY p.created_at, p.id LIMIT '.$limit.' OFFSET '.(($page - 1) * $limit),
            $params,
        );

        return new ParticipationListView($this->rows($organizer, $rows), $total, $page, $limit);
    }

    public function participation(OrganizerId $organizer, ParticipationId $id): ParticipationRowView
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT '.self::COLUMNS.' '.self::FROM.' WHERE p.organizer_id = :organizer AND p.id = :id',
            ['organizer' => $organizer->toString(), 'id' => $id->toString()],
        );

        return $this->rows($organizer, $rows)[0] ?? throw ParticipationNotFound::forId($id);
    }

    public function order(OrganizerId $organizer, OrderId $order): AdminOrderView
    {
        /** @var array<string, mixed>|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT id, number, event_id, orderer_first_name, orderer_last_name, orderer_email, orderer_phone, payment_method, origin, amount_due_minor, amount_due_currency, placed_at
               FROM registration.orders WHERE organizer_id = :organizer AND id = :id',
            ['organizer' => $organizer->toString(), 'id' => $order->toString()],
        );
        if (false === $row) {
            throw OrderNotFound::forId($order);
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT '.self::COLUMNS.' '.self::FROM.' WHERE p.organizer_id = :organizer AND p.order_id = :order ORDER BY p.created_at, p.id',
            ['organizer' => $organizer->toString(), 'order' => $order->toString()],
        );
        $currency = Currency::from(Cells::string($row['amount_due_currency']));
        $summary = $this->finance->summaries($organizer, [$order])[$order->toString()] ?? null;

        return new AdminOrderView(
            $order,
            Cells::string($row['number']),
            EventId::fromString(Cells::string($row['event_id'])),
            trim(Cells::string($row['orderer_first_name'] ?? '').' '.Cells::string($row['orderer_last_name'] ?? '')),
            Cells::string($row['orderer_email']),
            Cells::nullableString($row['orderer_phone']),
            PaymentMethod::from(Cells::string($row['payment_method'])),
            Cells::string($row['origin']),
            Money::of(Cells::int($row['amount_due_minor']), $currency),
            $summary?->state,
            null === $summary ? Money::zero($currency) : $summary->received,
            Cells::time($row['placed_at']),
            $this->rows($organizer, $rows),
        );
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<ParticipationRowView>
     */
    private function rows(OrganizerId $organizer, array $rows): array
    {
        $orders = array_values(array_unique(array_map(static fn(array $r): OrderId => OrderId::fromString(Cells::string($r['order_id'])), $rows), \SORT_REGULAR));
        $finance = [] === $orders ? [] : $this->finance->summaries($organizer, $orders);

        return array_map(function (array $r) use ($organizer, $finance): ParticipationRowView {
            $event = EventId::fromString(Cells::string($r['event_id']));
            $activity = ActivityId::fromString(Cells::string($r['activity_id']));
            $variant = VariantId::fromString(Cells::string($r['variant_id']));

            return new ParticipationRowView(
                ParticipationId::fromString(Cells::string($r['id'])),
                OrderId::fromString(Cells::string($r['order_id'])),
                $event,
                Cells::string($r['number']),
                PersonId::fromString(Cells::string($r['person_id'])),
                trim(Cells::string($r['first_name'] ?? '').' '.Cells::string($r['last_name'] ?? '')),
                Cells::nullableString($r['email']),
                Cells::string($r['registration_code']),
                $activity,
                $this->labels->activityName($organizer, $event, $activity),
                $variant,
                $this->labels->variantName($organizer, $event, $variant),
                ParticipationState::from(Cells::string($r['state'])),
                LegalState::from(Cells::string($r['legal_state'])),
                AnswersState::from(Cells::string($r['answers_state'])),
                Cells::nullableTime($r['hold_expires_at']),
                Money::of(Cells::int($r['price_minor']), Currency::from(Cells::string($r['price_currency']))),
                ($finance[Cells::string($r['order_id'])] ?? null)?->state,
                Cells::time($r['created_at']),
            );
        }, $rows);
    }
}
