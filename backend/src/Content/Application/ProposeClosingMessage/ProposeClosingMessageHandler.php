<?php

declare(strict_types=1);

namespace Eventor\Content\Application\ProposeClosingMessage;

use Eventor\Communication\Contract\CampaignAudience;
use Eventor\Communication\Contract\CampaignComposer;
use Eventor\Communication\Contract\ProposedCampaign;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Content\Application\Port\EventGuard;
use Eventor\Shared\Application\Tenant\TenantContext;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * POST-05: a draft "thank you, here are the photos and results" campaign for the checked-in participants only. The
 * organizer reviews and sends it in Communication, which owns and audits the campaign.
 */
#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('The campaign proposal is recorded and audited by Communication.')]
final readonly class ProposeClosingMessageHandler
{
    public function __construct(
        private CampaignComposer $campaigns,
        private EventGuard $events,
        private TenantContext $tenant,
    ) {}

    /**
     * @return array{campaignId: string}
     */
    public function __invoke(ProposeClosingMessage $command): array
    {
        $organizer = $this->tenant->require();
        $event = EventId::fromString($command->eventId);
        $this->events->assertExists($organizer, $event);

        $campaign = $this->campaigns->propose($organizer, new ProposedCampaign($event, TemplateKey::PostEvent->value, new CampaignAudience($event, [], [], true, checkedIn: true), null));

        return ['campaignId' => $campaign->toString()];
    }
}
