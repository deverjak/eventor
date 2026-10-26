<?php

declare(strict_types=1);

namespace Eventor\Communication\UserInterface\EventHandler;

use Eventor\Catalog\Contract\Event\NewsPublished;
use Eventor\Communication\Application\Campaign\ProposeCampaign;
use Eventor\Communication\Application\Campaign\StartCampaign;
use Eventor\Communication\Contract\CampaignAudienceSpec;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * POST-03: a news post published with "send to registered" goes to everybody registered for the event. The organizer
 * asked for it explicitly, so the campaign is started at once.
 */
#[AsMessageHandler(fromTransport: 'communication_events')]
final readonly class OnNewsPublished
{
    public function __construct(private CommandBus $commands) {}

    public function __invoke(NewsPublished $event): void
    {
        if (!$event->broadcast) {
            return;
        }

        $tenant = TenantStamp::of($event->organizerId);
        /** @var array{campaignId: string, created: bool} $proposed */
        $proposed = $this->commands->dispatch(new ProposeCampaign(
            $event->eventId->toString(),
            TemplateKey::NewsBroadcast,
            new CampaignAudienceSpec(),
            'news_broadcast:'.$event->newsPostId->toString(),
            $event->newsPostId->toString(),
        ), $tenant);

        $this->commands->dispatch(new StartCampaign($proposed['campaignId'], tolerateStarted: true), $tenant);
    }
}
