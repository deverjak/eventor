<?php

declare(strict_types=1);

namespace Eventor\Communication\UserInterface\EventHandler;

use Eventor\Catalog\Contract\Event\EventCompleted;
use Eventor\Communication\Application\Campaign\ProposeCampaign;
use Eventor\Communication\Application\Campaign\StartCampaign;
use Eventor\Communication\Contract\CampaignAudienceSpec;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * COM-01, "materiály po akci": the participants whose participation was confirmed get the thank-you message once the
 * event is completed.
 */
#[AsMessageHandler(fromTransport: 'communication_events')]
final readonly class OnEventCompleted
{
    public function __construct(private CommandBus $commands) {}

    public function __invoke(EventCompleted $event): void
    {
        $tenant = TenantStamp::of($event->organizerId);
        /** @var array{campaignId: string, created: bool} $proposed */
        $proposed = $this->commands->dispatch(new ProposeCampaign(
            $event->eventId->toString(),
            TemplateKey::PostEvent,
            new CampaignAudienceSpec(participationStates: ['confirmed']),
            'post_event:'.$event->eventId->toString(),
        ), $tenant);

        $this->commands->dispatch(new StartCampaign($proposed['campaignId'], tolerateStarted: true), $tenant);
    }
}
