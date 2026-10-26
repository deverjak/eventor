<?php

declare(strict_types=1);

namespace Eventor\Registration\UserInterface\EventHandler;

use Eventor\Legal\Contract\Event\ReconfirmationRequested;
use Eventor\Registration\Application\Legal\MarkReconfirmationRequired;
use Eventor\Shared\Application\Audit\AuditContext;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Eventor\Shared\Domain\Audit\AuditActor;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * `legal.reconfirmation_requested` (S15): the dependent participations get `legal_state = reconfirmation_required`.
 */
#[AsMessageHandler(fromTransport: 'registration_events')]
final readonly class OnReconfirmationRequested
{
    public function __construct(
        private CommandBus $commands,
        private AuditContext $audit,
    ) {}

    public function __invoke(ReconfirmationRequested $event): void
    {
        $this->audit->as(AuditActor::system('registration.mark_legal_incomplete'), fn(): mixed => $this->commands->dispatch(
            new MarkReconfirmationRequired($event->organizerId, $event->participationIds, $event->orderIds),
            TenantStamp::of($event->organizerId),
        ));
    }
}
