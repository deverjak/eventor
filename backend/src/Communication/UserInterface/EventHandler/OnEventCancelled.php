<?php

declare(strict_types=1);

namespace Eventor\Communication\UserInterface\EventHandler;

use Eventor\Catalog\Contract\Event\EventCancelled;
use Eventor\Communication\Application\Campaign\ProposeCampaign;
use Eventor\Communication\Application\Campaign\StartCampaign;
use Eventor\Communication\Contract\CampaignAudienceSpec;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * COM-01, "zrušení akce": everybody registered is told at once. Registration cancels the orders on the same event, in
 * parallel, so the audience includes the participations that are cancelled by then.
 */
#[AsMessageHandler(fromTransport: 'communication_events')]
final readonly class OnEventCancelled
{
    public function __construct(private CommandBus $commands) {}

    public function __invoke(EventCancelled $event): void
    {
        $tenant = TenantStamp::of($event->organizerId);
        /** @var array{campaignId: string, created: bool} $proposed */
        $proposed = $this->commands->dispatch(new ProposeCampaign(
            $event->eventId->toString(),
            TemplateKey::EventCancelled,
            new CampaignAudienceSpec(participationStates: ['pending', 'confirmed', 'requires_attention', 'cancelled']),
            'event_cancelled:'.$event->eventId->toString(),
        ), $tenant);

        $this->commands->dispatch(new StartCampaign($proposed['campaignId'], tolerateStarted: true), $tenant);
    }
}
