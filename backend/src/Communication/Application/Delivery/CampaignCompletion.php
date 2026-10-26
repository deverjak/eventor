<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Delivery;

use Eventor\Communication\Contract\CampaignState;
use Eventor\Communication\Contract\DeliveryState;
use Eventor\Communication\Domain\Campaign\CampaignRepository;
use Eventor\Communication\Domain\Delivery\Delivery;
use Eventor\Communication\Domain\Delivery\DeliveryRepository;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * COM-06: when the last waiting delivery of a sending campaign leaves the scheduled state, the campaign is sent
 * (`communication.campaign_sent`). The campaign row is locked first, so two workers finishing the last two deliveries
 * at once cannot both see "one still waiting".
 */
final readonly class CampaignCompletion
{
    public function __construct(
        private CampaignRepository $campaigns,
        private DeliveryRepository $deliveries,
        private WorkflowInterface $campaignStateMachine,
    ) {}

    public function afterLeavingScheduled(Delivery $delivery, \DateTimeImmutable $now): void
    {
        $campaignId = $delivery->campaignId();
        if (null === $campaignId) {
            return;
        }
        $campaign = $this->campaigns->findForUpdate($campaignId);
        if (null === $campaign || CampaignState::Sending !== $campaign->getState()) {
            return;
        }

        $progress = $this->deliveries->progressOfCampaign($campaignId, $delivery->id());
        if ($progress['waiting'] > 0) {
            return;
        }
        $state = $delivery->getState();
        $campaign->complete(
            $this->campaignStateMachine,
            $progress['sent'] + (DeliveryState::Sent === $state || DeliveryState::Delivered === $state ? 1 : 0),
            $progress['skipped'] + (DeliveryState::Skipped === $state ? 1 : 0),
            $now,
        );
    }
}
