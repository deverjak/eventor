<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Correct;

use Eventor\Catalog\Contract\EventCatalog;
use Eventor\Registration\Application\Answers\AnswersCompleteness;
use Eventor\Registration\Application\Legal\LegalStateRecalculator;
use Eventor\Registration\Application\PlaceOrder\SubmissionValidator;
use Eventor\Registration\Contract\Exception\PersonNotFound;
use Eventor\Registration\Contract\ParticipationState;
use Eventor\Registration\Domain\Answer\AnswerSet;
use Eventor\Registration\Domain\Answer\AnswerSetId;
use Eventor\Registration\Domain\Answer\AnswerSetRepository;
use Eventor\Registration\Domain\Eligibility\ParticipationEligibilityPolicy;
use Eventor\Registration\Domain\Order\OrderRepository;
use Eventor\Registration\Domain\Participation\Participation;
use Eventor\Registration\Domain\Participation\ParticipationRepository;
use Eventor\Registration\Domain\Person\Person;
use Eventor\Registration\Domain\Person\PersonRepository;
use Eventor\Shared\Domain\Id\PersonId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * REG-09, data correction (P0): validates the new answers against the current form version, re-checks the age
 * conditions of every participation of the person (a correction must not slip past them), adds the new answer set and
 * supersedes the old one, follows the semantic columns of the person (and their guardian) and recomputes the legal and
 * answers dimensions. The audit entry lists the changed field keys only.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class CorrectPersonDataHandler
{
    public function __construct(
        private OrderRepository $orders,
        private PersonRepository $persons,
        private ParticipationRepository $participations,
        private AnswerSetRepository $answerSets,
        private EventCatalog $catalog,
        private SubmissionValidator $validator,
        private LegalStateRecalculator $legal,
        private AnswersCompleteness $completeness,
        private ClockInterface $clock,
    ) {}

    public function __invoke(CorrectPersonData $command): void
    {
        $organizer = $command->organizerId;
        $order = $this->orders->get($command->orderId);
        $person = $this->persons->get($command->personId);
        $mine = array_values(array_filter(
            $this->participations->ofPerson($person->id()),
            static fn(Participation $p): bool => ParticipationState::Cancelled !== $p->state() && ParticipationState::Transferred !== $p->state(),
        ));
        if (!$person->orderId()->equals($command->orderId) || [] === $mine) {
            throw PersonNotFound::forId($command->personId);
        }

        $offer = $this->catalog->registrationSnapshot($organizer, $order->eventId());
        $set = $this->validator->validatePerson(
            $organizer,
            $offer->formVersionId,
            array_map(static fn(Participation $p) => $p->activityId(), $mine),
            array_map(static fn(Participation $p) => $p->variantId(), $mine),
            $command->answers,
        );

        foreach ($mine as $participation) {
            $activity = $offer->activity($participation->activityId());
            $variant = $offer->variant($participation->variantId());
            if (null !== $activity && null !== $variant) {
                ParticipationEligibilityPolicy::assertAge($set->semantic->birthDate, $offer->ageReferenceDate, $activity->minAge, $activity->maxAge, $activity->name);
                ParticipationEligibilityPolicy::assertAge($set->semantic->birthDate, $offer->ageReferenceDate, $variant->minAge, $variant->maxAge, $activity->name.' / '.$variant->name);
            }
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $old = $this->answerSets->current('person', $person->id()->toString());
        $new = new AnswerSet(AnswerSetId::generate(), $organizer, $order->id(), 'person', $person->id()->toString(), $offer->formVersionId, $set->answers, $now);
        null === $old ? $this->answerSets->add($new) : $this->answerSets->replace($old, $new);

        $semantic = $set->semantic;
        $person->correct($semantic->firstName, $semantic->lastName, $semantic->birthDate, $semantic->email, $semantic->phone);
        $this->followGuardian($person, $semantic->guardianFirstName, $semantic->guardianLastName, $semantic->guardianEmail, $semantic->guardianPhone);

        $this->legal->recompute($organizer, $mine);
        foreach ($mine as $participation) {
            $this->completeness->apply($organizer, $offer->formVersionId, $participation);
        }

        $order->noteDataCorrected('person', $person->id()->toString(), AnswerDiff::changedKeys($old?->answers() ?? [], $set->answers));
    }

    private function followGuardian(Person $child, ?string $first, ?string $last, ?string $email, ?string $phone): void
    {
        if (null === $first && null === $last && null === $email && null === $phone) {
            return;
        }

        $guardianId = $child->guardianPersonId();
        if (null !== $guardianId) {
            $this->persons->get($guardianId)->correct($first, $last, null, $email, $phone);

            return;
        }

        $guardian = new Person(PersonId::generate(), $child->organizerId(), $child->orderId(), $first, $last, null, $email, $phone);
        $this->persons->add($guardian);
        $child->assignGuardian($guardian->id());
    }
}
