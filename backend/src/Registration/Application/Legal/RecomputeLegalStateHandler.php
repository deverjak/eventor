<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Legal;

use Eventor\Registration\Contract\Exception\ParticipationNotFound;
use Eventor\Registration\Domain\Participation\ParticipationRepository;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('A decision that does not change the legal state (the usual case at registration) records nothing; a change records ParticipationChanged.')]
final readonly class RecomputeLegalStateHandler
{
    public function __construct(
        private ParticipationRepository $participations,
        private LegalStateRecalculator $recalculator,
    ) {}

    public function __invoke(RecomputeLegalState $command): void
    {
        if (null !== $command->participationId) {
            try {
                $targets = [$this->participations->get($command->participationId)];
            } catch (ParticipationNotFound) {
                return; // another organizer's or an unknown participation: a stale event
            }
        } elseif (null !== $command->orderId) {
            $targets = $this->participations->ofOrder($command->orderId);
        } else {
            return;
        }

        $this->recalculator->recompute($command->organizerId, $targets);
    }
}
