<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Application\RecordCashAndCheckIn;

use Eventor\CheckIn\Application\Desk\CheckInAssessor;
use Eventor\CheckIn\Application\Desk\CheckInListGate;
use Eventor\CheckIn\Contract\CheckInSituation;
use Eventor\CheckIn\Contract\Exception\AlreadyCheckedIn;
use Eventor\CheckIn\Contract\Exception\CashCheckInNotApplicable;
use Eventor\CheckIn\Contract\Exception\CheckInBlocked;
use Eventor\CheckIn\Domain\CheckInDecision;
use Eventor\CheckIn\Domain\CheckInRecord;
use Eventor\CheckIn\Domain\CheckInRecordRepository;
use Eventor\Payments\Contract\CashDesk;
use Eventor\Shared\Application\Tenant\TenantContext;
use Eventor\Shared\Domain\Currency;
use Eventor\Shared\Domain\Id\CheckInStationId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Money;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * CHK-08: an unpaid participant pays in cash at the desk. The cash entry of Payments (`CashDesk`, which refuses when the
 * organizer policy does not let this actor take cash) and the check-in share this transaction, so the operator never
 * sees "paid but not checked in". Registration confirms the participations later, from `payments.order_payment_changed`;
 * CheckIn does not wait for it.
 *
 * Applies to exactly one situation: unpaid and nothing else in the way (the matrix says `Warn: Unpaid`).
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class RecordCashAndCheckInHandler
{
    public function __construct(
        private CheckInListGate $gate,
        private CheckInAssessor $assessor,
        private CheckInRecordRepository $records,
        private CashDesk $cash,
        private TenantContext $tenant,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{checkInId: string, checkedInAt: string, ledgerEntryId: string, alreadyRecorded: bool, financialState: string, received: int, amountDue: int}
     */
    public function __invoke(RecordCashAndCheckIn $command): array
    {
        $organizer = $this->tenant->require();
        $list = $this->gate->open($command->eventId, $command->listId);
        $participation = ParticipationId::fromString($command->participationId);

        $this->records->lockParticipation($participation);
        $assessment = $this->assessor->assess($organizer, $list->activityId(), $participation);
        $verdict = $assessment->verdict;
        if (CheckInDecision::Block === $verdict->decision) {
            if (CheckInSituation::AlreadyCheckedIn === $verdict->situation && null !== $assessment->existing) {
                throw new AlreadyCheckedIn($assessment->existing->checkedInAt(), $assessment->existing->checkedInBy(), $assessment->existing->stationId(), $assessment->existing->deviceLabel());
            }
            throw new CheckInBlocked($verdict->situation ?? CheckInSituation::RequiresAttention);
        }
        if (CheckInDecision::Warn !== $verdict->decision || CheckInSituation::Unpaid !== $verdict->situation) {
            throw new CashCheckInNotApplicable($verdict->situation);
        }

        $station = null === $command->stationId ? null : CheckInStationId::fromString($command->stationId);
        $receipt = $this->cash->recordCash($organizer, $assessment->participation->orderId, Money::of($command->amountMinor, Currency::CZK), $station, $command->idempotencyKey, $command->note);

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $record = CheckInRecord::record($organizer, $participation, $list->id(), $station, $command->actorAccountId, $command->deviceLabel, $now, $command->amountMinor, $receipt->ledgerEntryId);
        $this->records->add($record);

        return [
            'checkInId' => $record->id()->toString(),
            'checkedInAt' => $record->checkedInAt()->setTimezone(new \DateTimeZone('UTC'))->format(\DATE_ATOM),
            'ledgerEntryId' => $receipt->ledgerEntryId->toString(),
            'alreadyRecorded' => $receipt->alreadyRecorded,
            'financialState' => $receipt->finance->state->value,
            'received' => $receipt->finance->received->minor,
            'amountDue' => $receipt->finance->amountDue->minor,
        ];
    }
}
