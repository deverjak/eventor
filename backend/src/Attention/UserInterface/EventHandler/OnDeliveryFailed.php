<?php

declare(strict_types=1);

namespace Eventor\Attention\UserInterface\EventHandler;

use Eventor\Attention\Application\Open\AttentionIntake;
use Eventor\Communication\Contract\Event\DeliveryFailed;
use Eventor\Shared\Application\Attention\AttentionType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A delivery failed for good; the failures of one campaign share one item.
 */
#[AsMessageHandler(fromTransport: 'attention_events')]
final readonly class OnDeliveryFailed
{
    public function __construct(private AttentionIntake $attention) {}

    public function __invoke(DeliveryFailed $event): void
    {
        $campaign = $event->campaignId;
        $this->attention->open(
            AttentionType::DeliveryFailed,
            $event->organizerId,
            null === $campaign ? 'delivery' : 'campaign',
            null === $campaign ? $event->deliveryId->toString() : $campaign->toString(),
            ['trigger' => $event->trigger, 'error' => $event->error, 'deliveryId' => $event->deliveryId->toString()],
        );
    }
}
