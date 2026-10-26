<?php

declare(strict_types=1);

namespace Eventor\CheckIn\UserInterface\EventHandler;

use Eventor\Catalog\Contract\Event\ActivityRescheduled;
use Eventor\CheckIn\Application\RescheduleList\RescheduleCheckInList;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * `catalog.activity_rescheduled` -> the list of the activity opens at the new start.
 */
#[AsMessageHandler(fromTransport: 'checkin_events')]
final readonly class OnActivityRescheduled
{
    public function __construct(private CommandBus $commands) {}

    public function __invoke(ActivityRescheduled $event): void
    {
        $this->commands->dispatch(new RescheduleCheckInList($event->activityId->toString(), $event->newStartsAt), TenantStamp::of($event->organizerId));
    }
}
