<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Application\RecordCheckIn;

use Eventor\CheckIn\Application\Desk\CheckInAssessor;
use Eventor\CheckIn\Application\Desk\CheckInListGate;
use Eventor\CheckIn\Contract\CheckInSituation;
use Eventor\CheckIn\Contract\Exception\AlreadyCheckedIn;
use Eventor\CheckIn\Contract\Exception\CheckInBlocked;
use Eventor\CheckIn\Contract\Exception\CheckInWarningNotAcknowledged;
use Eventor\CheckIn\Domain\CheckInDecision;
use Eventor\CheckIn\Domain\CheckInRecord;
use Eventor\CheckIn\Domain\CheckInRecordRepository;
use Eventor\Shared\Application\Tenant\TenantContext;
use Eventor\Shared\Domain\Id\CheckInStationId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * CHK-05, CHK-06, CHK-09. The rule matrix decides: `Allow` and an acknowledged `Warn` check in; a `Block` is refused
 * (409, nothing is written); an `Escalate` is a normal answer, not an error, because the escalation must be committed
 * (`checkin.escalation_raised` for Attention) while the participant stays outside.
 *
 * Two operators racing for the same participant (S10): the advisory lock of the participation lets the second one wait,
 * then see the winner's row and answer `already_checked_in`; the partial unique index is the backstop.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class RecordCheckInHandler
{
    public function __construct(
        private CheckInListGate $gate,
        private CheckInAssessor $assessor,
        private CheckInRecordRepository $records,
        private TenantContext $tenant,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{outcome: string, checkInId: ?string, checkedInAt: ?string, situation: ?string}
     */
    public function __invoke(RecordCheckIn $command): array
    {
        $organizer = $this->tenant->require();
        $list = $this->gate->open($command->eventId, $command->listId);
        $participation = ParticipationId::fromString($command->participationId);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $this->records->lockParticipation($participation);
        $assessment = $this->assessor->assess($organizer, $list->activityId(), $participation);
        $verdict = $assessment->verdict;

        switch ($verdict->decision) {
            case CheckInDecision::Block:
                if (CheckInSituation::AlreadyCheckedIn === $verdict->situation && null !== $assessment->existing) {
                    throw new AlreadyCheckedIn($assessment->existing->checkedInAt(), $assessment->existing->checkedInBy(), $assessment->existing->stationId(), $assessment->existing->deviceLabel());
                }
                throw new CheckInBlocked($verdict->situation ?? CheckInSituation::RequiresAttention);
            case CheckInDecision::Escalate:
                $situation = $verdict->situation ?? CheckInSituation::RequiresAttention;
                $list->escalate($participation, $situation, $now);

                return ['outcome' => 'escalated', 'checkInId' => null, 'checkedInAt' => null, 'situation' => $situation->value];
            case CheckInDecision::Warn:
                if (!$command->acknowledgeWarning) {
                    throw new CheckInWarningNotAcknowledged($verdict->situation ?? CheckInSituation::Unpaid);
                }
                break;
            case CheckInDecision::Allow:
                break;
        }

        $record = CheckInRecord::record(
            $organizer,
            $participation,
            $list->id(),
            null === $command->stationId ? null : CheckInStationId::fromString($command->stationId),
            $command->actorAccountId,
            $command->deviceLabel,
            $now,
        );
        $this->records->add($record);

        return ['outcome' => 'checked_in', 'checkInId' => $record->id()->toString(), 'checkedInAt' => $record->checkedInAt()->setTimezone(new \DateTimeZone('UTC'))->format(\DATE_ATOM), 'situation' => $verdict->situation?->value];
    }
}
