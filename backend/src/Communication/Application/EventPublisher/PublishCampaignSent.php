<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\EventPublisher;

use Eventor\Communication\Contract\Event\CampaignSent;
use Eventor\Communication\Domain\Event\CampaignCompleted;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Translates the domain event into `communication.campaign_sent` (Reporting).
 */
#[AsMessageHandler(bus: 'event.bus')]
final readonly class PublishCampaignSent
{
    public function __construct(
        private MessageBusInterface $eventBus,
        private ClockInterface $clock,
    ) {}

    public function __invoke(CampaignCompleted $event): void
    {
        $this->eventBus->dispatch(
            new CampaignSent($event->organizerId, $event->campaignId, $event->deliveryCount, $event->skippedCount, \DateTimeImmutable::createFromInterface($this->clock->now())),
            [TenantStamp::of($event->organizerId)],
        );
    }
}
