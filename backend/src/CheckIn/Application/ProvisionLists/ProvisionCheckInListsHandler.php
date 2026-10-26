<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Application\ProvisionLists;

use Eventor\Catalog\Contract\ActivitySummary;
use Eventor\Catalog\Contract\EventCatalog;
use Eventor\CheckIn\Domain\CheckInList;
use Eventor\CheckIn\Domain\CheckInListRepository;
use Eventor\Shared\Application\Tenant\TenantContext;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('A redelivery of catalog.event_published finds every list in place and creates nothing.')]
final readonly class ProvisionCheckInListsHandler
{
    public function __construct(
        private CheckInListRepository $lists,
        private EventCatalog $catalog,
        private TenantContext $tenant,
        private ClockInterface $clock,
    ) {}

    public function __invoke(ProvisionCheckInLists $command): void
    {
        $organizer = $this->tenant->require();
        $event = EventId::fromString($command->eventId);
        $activities = [];
        foreach ($this->catalog->summary($organizer, $event)->activities as $activity) {
            $activities[$activity->activityId->toString()] = $activity;
        }
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        foreach ($command->activityIds as $raw) {
            $activityId = ActivityId::fromString($raw);
            if (null !== $this->lists->findForActivity($activityId)) {
                continue;
            }
            $activity = $activities[$activityId->toString()] ?? null;
            \assert(null === $activity || $activity instanceof ActivitySummary);
            $this->lists->add(CheckInList::provision($organizer, $event, $activityId, null === $activity ? 'Check-in' : $activity->name, $activity?->startsAt, $now));
        }
    }
}
