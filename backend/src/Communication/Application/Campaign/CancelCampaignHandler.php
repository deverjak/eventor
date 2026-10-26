<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Campaign;

use Eventor\Communication\Contract\Exception\CampaignNotFound;
use Eventor\Communication\Domain\Campaign\CampaignRepository;
use Eventor\Shared\Domain\Id\CampaignId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class CancelCampaignHandler
{
    public function __construct(
        private CampaignRepository $campaigns,
        private WorkflowInterface $campaignStateMachine,
        private ClockInterface $clock,
    ) {}

    public function __invoke(CancelCampaign $command): void
    {
        $id = CampaignId::fromString($command->campaignId);
        $campaign = $this->campaigns->find($id) ?? throw CampaignNotFound::forId($id);
        $campaign->cancel($this->campaignStateMachine, \DateTimeImmutable::createFromInterface($this->clock->now()));
    }
}
