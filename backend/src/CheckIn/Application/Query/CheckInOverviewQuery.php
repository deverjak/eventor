<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\CheckIn\Application\Presenter\Cells;
use Eventor\CheckIn\Application\Query\View\OverviewRowView;
use Eventor\CheckIn\Application\Query\View\OverviewView;
use Eventor\Payments\Contract\FinanceQuery;
use Eventor\Registration\Contract\AudienceMember;
use Eventor\Registration\Contract\ParticipationState;
use Eventor\Shared\Domain\Finance\FinancialState;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\CheckInListId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * The operational overview of an event (CHK-10): expected / arrived / missing / unpaid / cash received, per activity.
 * Always computed live; Reporting builds the full dashboard from the events.
 */
final readonly class CheckInOverviewQuery
{
    public function __construct(
        private Connection $connection,
        private ExpectedParticipantsQuery $expected,
        private FinanceQuery $finance,
    ) {}

    /**
     * `$only` narrows the overview to one list (a device token bound to a list).
     */
    public function forEvent(OrganizerId $organizer, EventId $event, ?CheckInListId $only = null): OverviewView
    {
        /** @var list<array<string, mixed>> $lists */
        $lists = $this->connection->fetchAllAssociative(
            'SELECT l.id, l.activity_id, l.name, l.opens_at,
                    (SELECT COALESCE(sum(c.cash_minor), 0) FROM checkin.check_in c WHERE c.list_id = l.id AND c.voided_at IS NULL) AS cash
               FROM checkin.check_in_list l
              WHERE l.organizer_id = :organizer AND l.event_id = :event AND l.cancelled_at IS NULL
                AND (CAST(:only AS uuid) IS NULL OR l.id = CAST(:only AS uuid))
              ORDER BY l.opens_at NULLS LAST, l.name, l.id',
            ['organizer' => $organizer->toString(), 'event' => $event->toString(), 'only' => $only?->toString()],
        );
        $confirmed = $this->expected->byActivity($organizer, $event, [ParticipationState::Confirmed]);
        $active = $this->expected->byActivity($organizer, $event, [ParticipationState::Confirmed, ParticipationState::Pending, ParticipationState::RequiresAttention]);
        $unpaidOrders = $this->unpaidOrders($organizer, $active);

        $rows = [];
        foreach ($lists as $list) {
            $activity = Cells::string($list['activity_id']);
            $listId = CheckInListId::fromString(Cells::string($list['id']));
            $expectedIds = $this->participationIds($confirmed[$activity] ?? []);
            $arrived = $this->arrived($organizer, $listId);
            $unpaid = 0;
            foreach ($active[$activity] ?? [] as $member) {
                if (isset($unpaidOrders[$member->orderId->toString()])) {
                    $unpaid += \count($member->participationIds);
                }
            }
            $rows[] = new OverviewRowView(
                $listId,
                ActivityId::fromString($activity),
                Cells::string($list['name']),
                Cells::nullableTime($list['opens_at']),
                \count($expectedIds),
                \count($arrived),
                \count(array_diff($expectedIds, $arrived)),
                $unpaid,
                Cells::int($list['cash']),
            );
        }

        return new OverviewView(
            $event,
            $rows,
            array_sum(array_map(static fn(OverviewRowView $row): int => $row->expected, $rows)),
            array_sum(array_map(static fn(OverviewRowView $row): int => $row->checkedIn, $rows)),
            array_sum(array_map(static fn(OverviewRowView $row): int => $row->missing, $rows)),
            array_sum(array_map(static fn(OverviewRowView $row): int => $row->unpaid, $rows)),
            array_sum(array_map(static fn(OverviewRowView $row): int => $row->cashReceivedMinor, $rows)),
        );
    }

    /**
     * @param list<AudienceMember> $members
     *
     * @return list<string>
     */
    private function participationIds(array $members): array
    {
        $ids = [];
        foreach ($members as $member) {
            foreach ($member->participationIds as $id) {
                $ids[] = $id->toString();
            }
        }

        return $ids;
    }

    /**
     * @return list<string> participations with a valid check-in on the list
     */
    private function arrived(OrganizerId $organizer, CheckInListId $list): array
    {
        /** @var list<string> $ids */
        $ids = $this->connection->fetchFirstColumn(
            'SELECT participation_id FROM checkin.check_in WHERE organizer_id = :organizer AND list_id = :list AND voided_at IS NULL',
            ['organizer' => $organizer->toString(), 'list' => $list->toString()],
        );

        return $ids;
    }

    /**
     * @param array<string, list<AudienceMember>> $active
     *
     * @return array<string, true> order ids that are not paid in full
     */
    private function unpaidOrders(OrganizerId $organizer, array $active): array
    {
        $orders = [];
        foreach ($active as $members) {
            foreach ($members as $member) {
                $orders[$member->orderId->toString()] = $member->orderId;
            }
        }
        $unpaid = [];
        foreach ($this->finance->summaries($organizer, array_values($orders)) as $orderId => $summary) {
            if (!\in_array($summary->state, [FinancialState::Paid, FinancialState::Overpaid, FinancialState::NotRequired], true)) {
                $unpaid[(string) $orderId] = true;
            }
        }

        return $unpaid;
    }
}
