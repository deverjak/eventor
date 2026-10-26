<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Cancellation;

use Eventor\Catalog\Contract\EventCatalog;
use Eventor\Catalog\Contract\Exception\EventNotFound;
use Eventor\Registration\Contract\CancelledBy;
use Eventor\Registration\Contract\Exception\InvalidOrderRequest;
use Eventor\Registration\Contract\Exception\ParticipationNotFound;
use Eventor\Registration\Contract\OrderAmountChangeReason;
use Eventor\Registration\Domain\Exception\ParticipationTransitionNotAllowed;
use Eventor\Registration\Domain\Exception\SelfServiceActionNotAllowed;
use Eventor\Registration\Domain\Order\OrderRepository;
use Eventor\Registration\Domain\Participation\ParticipationRepository;
use Eventor\Shared\Domain\Id\EventId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Chapter 04, path 6: cancel one participation. A participant is bound by the cancellation rules of the event (switched
 * on, before the deadline, event not over); a team member needs a reason and the permission (the state machine guard).
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class CancelParticipationHandler
{
    public function __construct(
        private ParticipationRepository $participations,
        private OrderRepository $orders,
        private ParticipationCanceller $canceller,
        private EventCatalog $catalog,
        private ClockInterface $clock,
    ) {}

    public function __invoke(CancelParticipation $command): void
    {
        $participation = $this->participations->get($command->participationId);
        if (null !== $command->onlyOrder && !$participation->orderId()->equals($command->onlyOrder)) {
            throw ParticipationNotFound::forId($command->participationId);
        }

        if (!$participation->isActive()) {
            $state = $participation->state()->value;

            throw new ParticipationTransitionNotAllowed('cancel', $state, [], \sprintf('The transition "cancel" is not possible from the state "%s".', $state));
        }

        $reason = trim($command->reason ?? '');
        if (CancelledBy::Participant === $command->by) {
            $this->assertParticipantMayCancel($command, $participation->eventId());
            $reason = '' === $reason ? 'Cancelled by the participant' : $reason;
        } elseif (CancelledBy::Organizer === $command->by && '' === $reason) {
            throw InvalidOrderRequest::at('reason', 'reason_required', 'A reason is required when the organizer cancels a participation.');
        }

        $this->canceller->cancel(
            $this->orders->get($participation->orderId()),
            [$participation],
            $command->by,
            $reason,
            OrderAmountChangeReason::ParticipationCancelled,
        );
    }

    private function assertParticipantMayCancel(CancelParticipation $command, EventId $event): void
    {
        try {
            $offer = $this->catalog->registrationSnapshot($command->organizerId, $event);
        } catch (EventNotFound) {
            throw SelfServiceActionNotAllowed::cancellationDisabled();
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        if ($offer->endsAt <= $now) {
            throw SelfServiceActionNotAllowed::eventEnded();
        }
        if (!$offer->selfServiceCancellationAllowed) {
            throw SelfServiceActionNotAllowed::cancellationDisabled();
        }
        if (null !== $offer->selfServiceCancellationDeadline && $offer->selfServiceCancellationDeadline < $now) {
            throw SelfServiceActionNotAllowed::pastDeadline();
        }
    }
}
