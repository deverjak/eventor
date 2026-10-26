<?php

declare(strict_types=1);

namespace Eventor\Communication\UserInterface\EventHandler;

use Eventor\Communication\Application\Scheduling\ScheduleDelivery;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Registration\Contract\Event\SelfServiceLinkRequested;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * REG-11: a participant asked for the link to the self-service page again. The link is derived at send time
 * (`SelfServiceLinks`), the secret never travels on the bus.
 */
#[AsMessageHandler(fromTransport: 'communication_events')]
final readonly class OnSelfServiceLinkRequested
{
    public function __construct(private CommandBus $commands) {}

    public function __invoke(SelfServiceLinkRequested $event): void
    {
        $this->commands->dispatch(new ScheduleDelivery(
            TemplateKey::SelfServiceLink,
            'self_service_link:'.$event->tokenId->toString(),
            orderId: $event->orderId->toString(),
        ), TenantStamp::of($event->organizerId));
    }
}
