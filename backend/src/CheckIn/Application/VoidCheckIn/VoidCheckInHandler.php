<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Application\VoidCheckIn;

use Eventor\CheckIn\Application\Desk\EventCheckIns;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class VoidCheckInHandler
{
    public function __construct(
        private EventCheckIns $checkIns,
        private ClockInterface $clock,
    ) {}

    public function __invoke(VoidCheckIn $command): void
    {
        $this->checkIns->lock($command->eventId, $command->checkInId)
            ->void($command->actorAccountId, $command->reason, \DateTimeImmutable::createFromInterface($this->clock->now()));
    }
}
