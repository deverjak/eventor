<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Application\CancelLists;

use Eventor\CheckIn\Domain\CheckInListRepository;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('A redelivery finds the lists cancelled already.')]
final readonly class CancelCheckInListsHandler
{
    public function __construct(
        private CheckInListRepository $lists,
        private ClockInterface $clock,
    ) {}

    public function __invoke(CancelCheckInLists $command): void
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        foreach ($this->lists->forEvent(EventId::fromString($command->eventId)) as $list) {
            $list->cancel($now);
        }
    }
}
