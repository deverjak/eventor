<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Delivery;

use Eventor\Communication\Domain\Delivery\DeliveryRepository;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('Usually there is no waiting marketing delivery for the order and nothing changes.')]
final readonly class ExcludeFromMarketingHandler
{
    public function __construct(
        private DeliveryRepository $deliveries,
        private CampaignCompletion $completion,
        private WorkflowInterface $deliveryStateMachine,
        private ClockInterface $clock,
    ) {}

    /**
     * @return int how many waiting deliveries were skipped
     */
    public function __invoke(ExcludeFromMarketing $command): int
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $skipped = 0;
        foreach ($this->deliveries->waitingMarketingOfOrder(OrderId::fromString($command->orderId)) as $delivery) {
            $delivery->skip($this->deliveryStateMachine, 'consent_withdrawn', $now);
            $this->completion->afterLeavingScheduled($delivery, $now);
            ++$skipped;
        }

        return $skipped;
    }
}
