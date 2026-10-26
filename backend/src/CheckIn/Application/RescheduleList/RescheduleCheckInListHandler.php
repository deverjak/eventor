<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Application\RescheduleList;

use Eventor\CheckIn\Domain\CheckInListRepository;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('A redelivery, or an activity without a list, changes nothing.')]
final readonly class RescheduleCheckInListHandler
{
    public function __construct(
        private CheckInListRepository $lists,
        private ClockInterface $clock,
    ) {}

    public function __invoke(RescheduleCheckInList $command): void
    {
        $this->lists->findForActivity(ActivityId::fromString($command->activityId))
            ?->reschedule($command->newStartsAt, \DateTimeImmutable::createFromInterface($this->clock->now()));
    }
}
