<?php

declare(strict_types=1);

namespace Eventor\Communication\UserInterface\EventHandler;

use Eventor\Catalog\Contract\Event\ProgramItemChanged;
use Eventor\Communication\Application\Campaign\ProposeCampaign;
use Eventor\Communication\Contract\CampaignAudienceSpec;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * PRG-03: an important change of the program proposes a draft campaign; an item bound to an activity targets that
 * activity, an unbound one everybody registered for the event.
 */
#[AsMessageHandler(fromTransport: 'communication_events')]
final readonly class OnProgramItemChanged
{
    public function __construct(private CommandBus $commands) {}

    public function __invoke(ProgramItemChanged $event): void
    {
        if (!$event->important) {
            return;
        }

        $this->commands->dispatch(new ProposeCampaign(
            $event->eventId->toString(),
            TemplateKey::ProgramChanged,
            new CampaignAudienceSpec(activityIds: null === $event->activityId ? [] : [$event->activityId->toString()]),
            \sprintf('program_item_changed:%s:%d', $event->programItemId->toString(), $event->occurredAt->getTimestamp()),
        ), TenantStamp::of($event->organizerId));
    }
}
