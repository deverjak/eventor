<?php

declare(strict_types=1);

namespace Eventor\Communication\UserInterface\EventHandler;

use Eventor\Communication\Application\Campaign\StartCampaign;
use Eventor\Communication\Application\Scheduling\DispatchDelivery;
use Eventor\Communication\Application\Scheduling\DueWork;
use Eventor\Communication\Application\Scheduling\SweepCommunication;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Eventor\Shared\Domain\Id\OrganizerId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The scheduler tick: finds due deliveries and campaigns across organizers and hands each to its own command in its own
 * tenant and transaction, so one broken item does not hold up the others.
 */
#[AsMessageHandler(fromTransport: 'scheduler_communication')]
final readonly class SweepCommunicationSubscriber
{
    public function __construct(
        private DueWork $due,
        private CommandBus $commands,
        private ClockInterface $clock,
    ) {}

    public function __invoke(SweepCommunication $tick): void
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        foreach ($this->due->campaigns($now) as $campaign) {
            $this->commands->dispatch(new StartCampaign($campaign['id'], tolerateStarted: true), TenantStamp::of(OrganizerId::fromString($campaign['organizerId'])));
        }
        foreach ($this->due->deliveries($now) as $delivery) {
            $this->commands->dispatch(new DispatchDelivery($delivery['id']), TenantStamp::of(OrganizerId::fromString($delivery['organizerId'])));
        }
    }
}
