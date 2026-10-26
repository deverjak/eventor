<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Answers;

use Eventor\Registration\Contract\AnswersState;
use Eventor\Registration\Domain\Order\OrderRepository;
use Eventor\Registration\Domain\Participation\ParticipationRepository;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('A new form version that adds no required field to anyone changes nothing; a change records ParticipationChanged and registration.answers_incomplete.')]
final readonly class RequestMissingAnswersHandler
{
    public function __construct(
        private ParticipationRepository $participations,
        private OrderRepository $orders,
        private AnswersCompleteness $completeness,
    ) {}

    public function __invoke(RequestMissingAnswers $command): void
    {
        /** @var array<string, list<ParticipationId>> $newlyIncomplete by order id */
        $newlyIncomplete = [];
        foreach ($this->participations->activeOfEvent($command->eventId) as $participation) {
            $was = $participation->answersState();
            $incomplete = $this->completeness->apply($command->organizerId, $command->formVersionId, $participation);
            if ($incomplete && AnswersState::Incomplete !== $was) {
                $newlyIncomplete[$participation->orderId()->toString()][] = $participation->id();
            }
        }

        foreach ($newlyIncomplete as $orderId => $ids) {
            $this->orders->get(OrderId::fromString($orderId))->noteAnswersIncomplete($ids, $command->formVersionId);
        }
    }
}
