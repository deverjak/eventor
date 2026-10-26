<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Delivery;

use Eventor\Communication\Application\Scheduling\DeliveryScheduler;
use Eventor\Communication\Contract\Exception\CampaignNotFound;
use Eventor\Communication\Domain\Campaign\CampaignRepository;
use Eventor\Communication\Domain\Delivery\DeliveryRepository;
use Eventor\Communication\Domain\Exception\DeliveryNotFound;
use Eventor\Communication\Domain\Exception\DeliveryNotRetryable;
use Eventor\Shared\Domain\Id\CampaignId;
use Eventor\Shared\Domain\Id\DeliveryId;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('A retry request can find no failed deliveries; retries that do exist record DeliveryRetried.')]
final readonly class RetryFailedDeliveriesHandler
{
    public function __construct(
        private DeliveryRepository $deliveries,
        private CampaignRepository $campaigns,
        private DeliveryScheduler $scheduler,
        private WorkflowInterface $deliveryStateMachine,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{retried: int}
     */
    public function __invoke(RetryFailedDeliveries $command): array
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        if (null !== $command->deliveryId) {
            $delivery = $this->deliveries->find(DeliveryId::fromString($command->deliveryId)) ?? throw DeliveryNotFound::forId($command->deliveryId);
            if (\Eventor\Communication\Contract\DeliveryState::Failed !== $delivery->getState()) {
                throw DeliveryNotRetryable::inState($delivery->getState()->value);
            }
            $failed = [$delivery];
        } else {
            $campaign = null;
            if (null !== $command->campaignId) {
                $campaign = CampaignId::fromString($command->campaignId);
                $this->campaigns->find($campaign) ?? throw CampaignNotFound::forId($campaign);
            }
            $failed = $this->deliveries->failed($campaign);
        }

        foreach ($failed as $delivery) {
            $delivery->retry($this->deliveryStateMachine, $now);
            $this->scheduler->queue($delivery);
        }

        return ['retried' => \count($failed)];
    }
}
