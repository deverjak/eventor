<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Legal;

use Eventor\Registration\Contract\Exception\ParticipationNotFound;
use Eventor\Registration\Contract\LegalState;
use Eventor\Registration\Contract\ParticipationState;
use Eventor\Registration\Domain\Participation\Participation;
use Eventor\Registration\Domain\Participation\ParticipationRepository;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('Participations that are cancelled, expired or already marked change nothing; a change records ParticipationChanged.')]
final readonly class MarkReconfirmationRequiredHandler
{
    public function __construct(private ParticipationRepository $participations) {}

    public function __invoke(MarkReconfirmationRequired $command): void
    {
        $targets = [];
        foreach ($command->participationIds as $id) {
            try {
                $targets[$id->toString()] = $this->participations->get($id);
            } catch (ParticipationNotFound) {
                continue;
            }
        }
        foreach ($command->orderIds as $order) {
            foreach ($this->participations->ofOrder($order) as $participation) {
                $targets[$participation->id()->toString()] = $participation;
            }
        }

        foreach ($targets as $participation) {
            if (\in_array($participation->state(), [ParticipationState::Pending, ParticipationState::Confirmed, ParticipationState::RequiresAttention], true)) {
                $this->mark($participation);
            }
        }
    }

    private function mark(Participation $participation): void
    {
        $participation->changeLegalState(LegalState::ReconfirmationRequired, 'A new version of a document needs to be confirmed');
    }
}
