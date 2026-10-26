<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Campaign;

use Eventor\Communication\Contract\Exception\CampaignNotFound;
use Eventor\Communication\Domain\Campaign\CampaignRepository;
use Eventor\Shared\Domain\Id\CampaignId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UpdateCampaignHandler
{
    public function __construct(
        private CampaignRepository $campaigns,
        private CampaignDrafting $drafting,
        private ClockInterface $clock,
    ) {}

    public function __invoke(UpdateCampaign $command): void
    {
        $id = CampaignId::fromString($command->campaignId);
        $campaign = $this->campaigns->find($id) ?? throw CampaignNotFound::forId($id);
        $this->drafting->assertTarget($campaign->organizerId(), $campaign->eventId(), $command->audience);
        $wording = $this->drafting->wording($command->templateKey, $command->locale, $command->subject, $command->body);

        $campaign->edit(
            $command->name,
            $command->purpose,
            $command->templateKey,
            $command->locale,
            $wording['subject'],
            $wording['body'],
            $command->audience,
            \DateTimeImmutable::createFromInterface($this->clock->now()),
        );
    }
}
