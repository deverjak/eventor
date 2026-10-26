<?php

declare(strict_types=1);

namespace Eventor\Registration\UserInterface\EventHandler;

use Eventor\Legal\Contract\Event\ConfirmationRecorded;
use Eventor\Registration\Application\Legal\RecomputeLegalState;
use Eventor\Shared\Application\Audit\AuditContext;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Eventor\Shared\Domain\Audit\AuditActor;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * `legal.confirmation_recorded`: a decision may complete (or withdraw) what a participation owes; recompute its legal state.
 */
#[AsMessageHandler(fromTransport: 'registration_events')]
final readonly class OnConfirmationRecorded
{
    public function __construct(
        private CommandBus $commands,
        private AuditContext $audit,
    ) {}

    public function __invoke(ConfirmationRecorded $event): void
    {
        $this->audit->as(AuditActor::system('registration.recompute_legal_state'), fn(): mixed => $this->commands->dispatch(
            new RecomputeLegalState($event->organizerId, $event->participationId, $event->orderId),
            TenantStamp::of($event->organizerId),
        ));
    }
}
