<?php

declare(strict_types=1);

namespace Eventor\Communication\UserInterface\EventHandler;

use Eventor\Communication\Application\Delivery\ExcludeFromMarketing;
use Eventor\Communication\Application\Scheduling\ScheduleDelivery;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Legal\Contract\Event\ConsentWithdrawn;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * S16, PRV-07: marketing still waiting for this order is skipped at once, and the person gets the confirmation that the
 * consent was withdrawn. (The marketing gate also re-checks the consent on every send, so nothing slips through.)
 */
#[AsMessageHandler(fromTransport: 'communication_events')]
final readonly class OnConsentWithdrawn
{
    public function __construct(private CommandBus $commands) {}

    public function __invoke(ConsentWithdrawn $event): void
    {
        $tenant = TenantStamp::of($event->organizerId);

        $this->commands->dispatch(new ExcludeFromMarketing($event->orderId->toString()), $tenant);
        $this->commands->dispatch(new ScheduleDelivery(
            TemplateKey::ConsentWithdrawn,
            'consent_withdrawn:'.$event->confirmationId->toString(),
            orderId: $event->orderId->toString(),
        ), $tenant);
    }
}
