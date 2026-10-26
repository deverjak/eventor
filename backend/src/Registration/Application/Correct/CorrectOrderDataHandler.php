<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Correct;

use Eventor\Catalog\Contract\EventCatalog;
use Eventor\Registration\Application\PlaceOrder\SubmissionValidator;
use Eventor\Registration\Domain\Answer\AnswerSet;
use Eventor\Registration\Domain\Answer\AnswerSetId;
use Eventor\Registration\Domain\Answer\AnswerSetRepository;
use Eventor\Registration\Domain\Order\Orderer;
use Eventor\Registration\Domain\Order\OrderRepository;
use Eventor\Registration\Domain\Participation\Participation;
use Eventor\Registration\Domain\Participation\ParticipationRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * REG-09, data correction of the order contact (the most common fix is a typo in the e-mail address).
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class CorrectOrderDataHandler
{
    public function __construct(
        private OrderRepository $orders,
        private ParticipationRepository $participations,
        private AnswerSetRepository $answerSets,
        private EventCatalog $catalog,
        private SubmissionValidator $validator,
        private ClockInterface $clock,
    ) {}

    public function __invoke(CorrectOrderData $command): void
    {
        $order = $this->orders->get($command->orderId);
        $offer = $this->catalog->registrationSnapshot($command->organizerId, $order->eventId());
        $all = $this->participations->ofOrder($order->id());

        $set = $this->validator->validateOrder(
            $command->organizerId,
            $offer->formVersionId,
            array_values(array_unique(array_map(static fn(Participation $p) => $p->activityId(), $all), \SORT_REGULAR)),
            array_values(array_unique(array_map(static fn(Participation $p) => $p->variantId(), $all), \SORT_REGULAR)),
            $command->answers,
        );

        $old = $this->answerSets->current('order', $order->id()->toString());
        $new = new AnswerSet(AnswerSetId::generate(), $command->organizerId, $order->id(), 'order', $order->id()->toString(), $offer->formVersionId, $set->answers, \DateTimeImmutable::createFromInterface($this->clock->now()));
        null === $old ? $this->answerSets->add($new) : $this->answerSets->replace($old, $new);

        $semantic = $set->semantic;
        $current = $order->orderer();
        $order->correctOrderer(new Orderer($semantic->firstName, $semantic->lastName, $semantic->email ?? $current->email, $semantic->phone, $current->locale));
        $order->noteDataCorrected('order', $order->id()->toString(), AnswerDiff::changedKeys($old?->answers() ?? [], $set->answers));
    }
}
