<?php

declare(strict_types=1);

namespace Eventor\Communication\UserInterface\EventHandler;

use Eventor\Catalog\Contract\Event\ActivityRescheduled;
use Eventor\Communication\Application\Campaign\ProposeCampaign;
use Eventor\Communication\Application\Reminder\ReplanPreEventReminders;
use Eventor\Communication\Contract\CampaignAudienceSpec;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * S12, chapter 04 path 10: the reminders that wait follow the new start; an important change also proposes the
 * "notify those affected" campaign (a draft: sending is the organizer's decision) for the people of that activity only.
 */
#[AsMessageHandler(fromTransport: 'communication_events')]
final readonly class OnActivityRescheduled
{
    public function __construct(private CommandBus $commands) {}

    public function __invoke(ActivityRescheduled $event): void
    {
        $tenant = TenantStamp::of($event->organizerId);

        $this->commands->dispatch(new ReplanPreEventReminders($event->activityId->toString(), \DateTimeImmutable::createFromInterface($event->newStartsAt)), $tenant);

        if ($event->important) {
            $this->commands->dispatch(new ProposeCampaign(
                $event->eventId->toString(),
                TemplateKey::ActivityChanged,
                new CampaignAudienceSpec(activityIds: [$event->activityId->toString()]),
                \sprintf('activity_rescheduled:%s:%d', $event->activityId->toString(), $event->newStartsAt->getTimestamp()),
            ), $tenant);
        }
    }
}
