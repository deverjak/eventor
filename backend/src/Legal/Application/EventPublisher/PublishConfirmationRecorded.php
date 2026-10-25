<?php

declare(strict_types=1);

namespace Eventor\Legal\Application\EventPublisher;

use Eventor\Legal\Contract\ConsentDecision;
use Eventor\Legal\Contract\Event\ConfirmationRecorded;
use Eventor\Legal\Contract\Event\ConsentWithdrawn;
use Eventor\Legal\Domain\Event\ConfirmationAppended;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Translates a ledger append into `legal.confirmation_recorded` and, for a withdrawal, additionally
 * `legal.consent_withdrawn` (S16: the registration stays valid, Attention and Communication react).
 */
#[AsMessageHandler(bus: 'event.bus')]
final readonly class PublishConfirmationRecorded
{
    public function __construct(
        private MessageBusInterface $eventBus,
        private ClockInterface $clock,
    ) {}

    public function __invoke(ConfirmationAppended $event): void
    {
        $stamp = TenantStamp::of($event->organizerId);
        $now = $this->clock->now();

        $this->eventBus->dispatch(
            new ConfirmationRecorded(
                $event->organizerId,
                $event->confirmationId,
                $event->requirementId,
                $event->versionId,
                $event->decision,
                $event->participationId,
                $event->orderId,
                $event->channel,
                $now,
            ),
            [$stamp],
        );

        if (ConsentDecision::Withdrawn === $event->decision && null !== $event->orderId) {
            $this->eventBus->dispatch(
                new ConsentWithdrawn(
                    $event->organizerId,
                    $event->confirmationId,
                    $event->requirementId,
                    $event->requirementType,
                    $event->participationId,
                    $event->orderId,
                    $now,
                ),
                [$stamp],
            );
        }
    }
}
