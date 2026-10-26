<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Application\CorrectCheckIn;

use Eventor\CheckIn\Application\Desk\EventCheckIns;
use Eventor\CheckIn\Domain\CheckInRecordRepository;
use Eventor\Shared\Domain\Id\CheckInStationId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class CorrectCheckInHandler
{
    public function __construct(
        private EventCheckIns $checkIns,
        private CheckInRecordRepository $records,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{checkInId: string, replacedCheckInId: string}
     */
    public function __invoke(CorrectCheckIn $command): array
    {
        $old = $this->checkIns->lock($command->eventId, $command->checkInId);
        $new = $old->replacement(
            null === $command->stationId ? null : CheckInStationId::fromString($command->stationId),
            $command->checkedInAt,
            $command->actorAccountId,
            null,
        );
        $old->void($command->actorAccountId, $command->reason, \DateTimeImmutable::createFromInterface($this->clock->now()), $new->id());
        // The unique index admits one valid row per participation: the void must be written before the replacement.
        $this->records->flush();
        $this->records->add($new);

        return ['checkInId' => $new->id()->toString(), 'replacedCheckInId' => $old->id()->toString()];
    }
}
