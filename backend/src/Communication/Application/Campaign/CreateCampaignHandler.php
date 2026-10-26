<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Campaign;

use Eventor\Communication\Domain\Campaign\Campaign;
use Eventor\Communication\Domain\Campaign\CampaignRepository;
use Eventor\Shared\Application\Tenant\TenantContext;
use Eventor\Shared\Domain\Id\CampaignId;
use Eventor\Shared\Domain\Id\EventId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateCampaignHandler
{
    public function __construct(
        private CampaignRepository $campaigns,
        private CampaignDrafting $drafting,
        private TenantContext $tenant,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{campaignId: string}
     */
    public function __invoke(CreateCampaign $command): array
    {
        $organizer = $this->tenant->require();
        $event = EventId::fromString($command->eventId);
        $this->drafting->assertTarget($organizer, $event, $command->audience);
        $wording = $this->drafting->wording($command->templateKey, $command->locale, $command->subject, $command->body);

        $campaign = Campaign::draft(
            CampaignId::generate(),
            $organizer,
            $event,
            $command->name,
            $command->purpose,
            $command->templateKey,
            $command->locale,
            $wording['subject'],
            $wording['body'],
            $command->audience,
            \DateTimeImmutable::createFromInterface($this->clock->now()),
        );
        $this->campaigns->add($campaign);

        return ['campaignId' => $campaign->id()->toString()];
    }
}
