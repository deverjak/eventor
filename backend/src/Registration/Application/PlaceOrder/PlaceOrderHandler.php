<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\PlaceOrder;

use Eventor\Catalog\Contract\EventCatalog;
use Eventor\Catalog\Contract\PublicationState;
use Eventor\Catalog\Contract\PublishedEventSnapshot;
use Eventor\Catalog\Contract\SemanticAnswers;
use Eventor\Legal\Contract\ApplicableRequirement;
use Eventor\Legal\Contract\ConfirmationChannel;
use Eventor\Legal\Contract\ConfirmationRecorder;
use Eventor\Legal\Contract\ConfirmationToRecord;
use Eventor\Legal\Contract\Confirmer;
use Eventor\Legal\Contract\RequirementResolver;
use Eventor\Payments\Contract\OrderFinanceInitializer;
use Eventor\Registration\Application\PaymentMethod\PaymentMethodStrategies;
use Eventor\Registration\Application\PaymentMethod\PaymentMethodStrategy;
use Eventor\Registration\Application\SelfService\SelfServiceTokens;
use Eventor\Registration\Contract\Exception\InvalidOrderRequest;
use Eventor\Registration\Contract\Exception\RegistrationNotOpen;
use Eventor\Registration\Domain\Answer\AnswerSet;
use Eventor\Registration\Domain\Answer\AnswerSetId;
use Eventor\Registration\Domain\Answer\AnswerSetRepository;
use Eventor\Registration\Domain\Capacity\CapacityAllocator;
use Eventor\Registration\Domain\Capacity\SeatClaim;
use Eventor\Registration\Domain\Eligibility\ParticipationEligibilityPolicy;
use Eventor\Registration\Domain\Order\Order;
use Eventor\Registration\Domain\Order\Orderer;
use Eventor\Registration\Domain\Order\OrderNumbers;
use Eventor\Registration\Domain\Order\OrderRepository;
use Eventor\Registration\Domain\Participation\Participation;
use Eventor\Registration\Domain\Participation\ParticipationRepository;
use Eventor\Registration\Domain\Participation\RegistrationCodes;
use Eventor\Registration\Domain\Person\Person;
use Eventor\Registration\Domain\Person\PersonRepository;
use Eventor\Registration\Domain\Pricing\PriceCalculator;
use Eventor\Registration\Domain\SelfService\SelfServiceTokenRepository;
use Eventor\Shared\Domain\Id\CapacityBucketId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\PersonId;
use Eventor\Shared\Domain\Money;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Chapter 04, path 2: the registration as ONE transaction (REG-01..03, REG-05, REG-06, S01, S02, S13). Order of work:
 * offer -> structure -> answers -> eligibility -> price -> legal requirements -> capacity (all buckets of all
 * participations or none) -> aggregate -> confirmations -> finance -> self-service token. Any exception rolls the
 * whole thing back, so a full bucket leaves no `held` seat behind. The response carries no payment data: the payment
 * request is created from `registration.order_placed` and the self-service page shows it when it exists (PAY-03).
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class PlaceOrderHandler
{
    public function __construct(
        private EventCatalog $catalog,
        private ItemResolver $itemResolver,
        private SubmissionValidator $validator,
        private PaymentMethodStrategies $strategies,
        private LegalDecisions $legalDecisions,
        private RequirementResolver $requirements,
        private ConfirmationRecorder $confirmations,
        private OrderFinanceInitializer $finance,
        private CapacityAllocator $capacity,
        private OrderRepository $orders,
        private PersonRepository $persons,
        private ParticipationRepository $participations,
        private AnswerSetRepository $answerSets,
        private SelfServiceTokenRepository $tokenRepository,
        private SelfServiceTokens $tokens,
        private OrderNumbers $numbers,
        private RegistrationCodes $codes,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{orderId: string, orderNumber: string, selfServiceUrl: string, payment: array{status: string}}
     */
    public function __invoke(PlaceOrder $command): array
    {
        $organizer = $command->organizerId;
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $offer = $this->catalog->registrationSnapshot($organizer, $command->eventId);
        if (PublicationState::Published !== $offer->publicationState || !$offer->acceptsRegistrations($now)) {
            throw RegistrationNotOpen::forEvent($offer->eventId);
        }

        $items = $this->itemResolver->resolve($command, $offer);
        $submission = $this->validator->validate($organizer, $offer->formVersionId, $command->items, $command->persons, $command->orderAnswers);

        // Eligibility and the minor flag from the birth date at the reference date of the event.
        $items = array_map(function (ResolvedItem $item) use ($submission, $offer): ResolvedItem {
            $birthDate = ($submission->persons[$item->input->personRef] ?? null)?->semantic->birthDate;
            ParticipationEligibilityPolicy::assertAge($birthDate, $offer->ageReferenceDate, $item->activity->minAge, $item->activity->maxAge, $item->activity->name);
            ParticipationEligibilityPolicy::assertAge($birthDate, $offer->ageReferenceDate, $item->variant->minAge, $item->variant->maxAge, $item->activity->name.' / '.$item->variant->name);

            return $item->withPerson($birthDate, ParticipationEligibilityPolicy::isMinor($birthDate, $offer->ageReferenceDate));
        }, $items);

        $total = PriceCalculator::total(array_map(static fn(ResolvedItem $item): Money => $item->variant->price, $items), $offer->currency);
        $strategy = $this->strategies->choose($offer, $command->paymentMethod, $total->isZero());

        // Legal requirements of this combination, and the decisions that answer them (REG-06, LEG-11).
        $required = $this->requirements->forRegistration($organizer, $command->eventId, $this->legalDecisions->slots($items));
        $decisions = $this->legalDecisions->resolve($command->decisions, $items, $required);
        $this->requirements->assertSatisfied($required, $this->legalDecisions->submitted($decisions));
        $this->assertGuardians($required->perSlot, $items, $submission);

        // Capacity: every bucket of every participation, all or nothing (S01, S02).
        $claims = $this->claims($items, $offer, $strategy);
        if ([] !== $claims) {
            $this->capacity->claim($organizer, $claims, false);
        }

        $orderId = OrderId::generate();
        $holdUntil = $strategy->holdsSeat() ? $this->holdUntil($offer, $now) : null;
        $orderer = $this->orderer($submission->order->semantic, $submission, $command->locale);

        // The aggregate: persons (guardians first), participations, answers.
        $personIds = [];
        foreach ($command->persons as $input) {
            $personIds[$input->personRef] = PersonId::generate();
        }
        $guardianIds = $this->guardians($submission, $orderId, $organizer, $personIds);
        foreach ($command->persons as $input) {
            $semantic = $submission->persons[$input->personRef]->semantic;
            $personId = $personIds[$input->personRef];
            $this->persons->add(new Person($personId, $organizer, $orderId, $semantic->firstName, $semantic->lastName, $semantic->birthDate, $semantic->email, $semantic->phone, $guardianIds[$input->personRef] ?? null));
            $this->answerSets->add(new AnswerSet(AnswerSetId::generate(), $organizer, $orderId, 'person', $personId->toString(), $offer->formVersionId, $submission->persons[$input->personRef]->answers, $now));
        }
        $this->answerSets->add(new AnswerSet(AnswerSetId::generate(), $organizer, $orderId, 'order', $orderId->toString(), $offer->formVersionId, $submission->order->answers, $now));

        $participationIds = [];
        $usedCodes = [];
        foreach ($items as $item) {
            $code = $this->codes->next();
            while (isset($usedCodes[$code])) {
                $code = $this->codes->next();
            }
            $usedCodes[$code] = true;
            $participationIds[] = $item->participationId;
            $this->participations->add(Participation::create(
                $item->participationId,
                $organizer,
                $orderId,
                $personIds[$item->input->personRef],
                $command->eventId,
                $item->activity->id,
                $item->variant->id,
                null,
                $holdUntil,
                $code,
                null,
                $item->variant->price,
                $now,
            ));
        }

        $number = $this->numbers->next($organizer, $now);
        $this->orders->add(Order::place($orderId, $organizer, $command->eventId, $number, $orderer, $strategy->method(), $total, $command->origin, $offer->formVersionId, $participationIds, $holdUntil, $now));

        // Confirmations of Legal in this transaction; `orderId` always goes with them (S15 recomputes per order).
        $toRecord = $this->toRecord($decisions, $personIds, $guardianIds, $orderId, $organizer, $orderer, $now);
        if ([] !== $toRecord) {
            $this->confirmations->record($organizer, $toRecord);
        }

        $this->finance->initialize($organizer, $orderId, $strategy->method(), $total);

        $token = $this->tokens->issue($organizer, $orderId, $offer->endsAt, 'order_placed');
        $this->tokenRepository->add($token);

        return [
            'orderId' => $orderId->toString(),
            'orderNumber' => $number,
            'selfServiceUrl' => $this->tokens->urlFor($token->id()),
            'payment' => ['status' => $strategy->paymentStatus()],
        ];
    }

    /**
     * The seats of one participation in the buckets of its levels: event, activity, variant (a missing bucket id means
     * "no limit at this level").
     *
     * @param list<ResolvedItem> $items
     *
     * @return list<SeatClaim>
     */
    private function claims(array $items, PublishedEventSnapshot $offer, PaymentMethodStrategy $strategy): array
    {
        $claims = [];
        foreach ($items as $item) {
            /** @var list<CapacityBucketId> $buckets */
            $buckets = array_values(array_filter([$offer->eventBucketId, $item->activity->capacityBucketId, $item->variant->capacityBucketId]));
            foreach ($buckets as $bucket) {
                $claims[] = new SeatClaim($item->participationId, $bucket, 1, $strategy->claimKind());
            }
        }

        return $claims;
    }

    /** The reservation lasts the TTL of the event but never beyond the close of the registration (chapter 05). */
    private function holdUntil(PublishedEventSnapshot $offer, \DateTimeImmutable $now): \DateTimeImmutable
    {
        $until = $now->modify('+'.$offer->holdTtlHours.' hours');

        return null !== $offer->registrationClosesAt && $offer->registrationClosesAt < $until ? $offer->registrationClosesAt : $until;
    }

    private function orderer(SemanticAnswers $contact, ValidatedSubmission $submission, string $locale): Orderer
    {
        $email = $contact->email;
        if (null === $email) {
            foreach ($submission->persons as $set) {
                $email ??= $set->semantic->email ?? $set->semantic->guardianEmail;
            }
        }
        if (null === $email) {
            throw InvalidOrderRequest::at('order.email', 'orderer_email_missing', 'The order needs an e-mail address for the confirmation.');
        }

        return new Orderer($contact->firstName, $contact->lastName, $email, $contact->phone, $locale);
    }

    /**
     * The guardian of every minor, by the child's reference. One person per distinct guardian in the order, and a guardian
     * who is also a participant of the order (a parent registering for Saturday and the children for Sunday, S13) is that
     * participant, not a second person: the match is the first and last name (and the e-mail when both sides have one).
     *
     * @param array<string, PersonId> $personIds by personRef
     *
     * @return array<string, PersonId> guardian by the personRef of the child
     */
    private function guardians(ValidatedSubmission $submission, OrderId $order, OrganizerId $organizer, array $personIds): array
    {
        $created = [];
        $result = [];
        foreach ($submission->persons as $ref => $set) {
            $child = $set->semantic;
            if (null === $child->guardianFirstName && null === $child->guardianLastName && null === $child->guardianEmail && null === $child->guardianPhone) {
                continue;
            }
            $ref = (string) $ref;
            $key = mb_strtolower(($child->guardianFirstName ?? '').'|'.($child->guardianLastName ?? '').'|'.($child->guardianEmail ?? ''));

            $participant = null;
            foreach ($submission->persons as $otherRef => $other) {
                $otherRef = (string) $otherRef;
                $sameName = null !== $child->guardianFirstName && null !== $child->guardianLastName
                    && mb_strtolower($child->guardianFirstName) === mb_strtolower((string) $other->semantic->firstName)
                    && mb_strtolower($child->guardianLastName) === mb_strtolower((string) $other->semantic->lastName);
                $sameEmail = null === $child->guardianEmail || null === $other->semantic->email || mb_strtolower($child->guardianEmail) === mb_strtolower($other->semantic->email);
                if ($otherRef !== $ref && $sameName && $sameEmail) {
                    $participant = $personIds[$otherRef] ?? null;
                    break;
                }
            }

            if (null !== $participant) {
                $result[$ref] = $participant;
                continue;
            }
            if (!isset($created[$key])) {
                $created[$key] = PersonId::generate();
                $this->persons->add(new Person($created[$key], $organizer, $order, $child->guardianFirstName, $child->guardianLastName, null, $child->guardianEmail, $child->guardianPhone));
            }
            $result[$ref] = $created[$key];
        }

        return $result;
    }

    /**
     * A minor needs a guardian wherever a requirement is confirmed by the guardian (LEG-11, S13).
     *
     * @param array<string, list<ApplicableRequirement>> $perSlot
     * @param list<ResolvedItem>                                                 $items
     */
    private function assertGuardians(array $perSlot, array $items, ValidatedSubmission $submission): void
    {
        foreach ($items as $item) {
            if (!$item->minor) {
                continue;
            }
            $semantic = ($submission->persons[$item->input->personRef] ?? null)?->semantic;
            $hasGuardian = null !== $semantic && (null !== $semantic->guardianFirstName || null !== $semantic->guardianLastName || null !== $semantic->guardianEmail);
            foreach ($perSlot[$item->itemRef] ?? [] as $requirement) {
                if (Confirmer::Guardian === $requirement->confirmer && !$hasGuardian) {
                    throw InvalidOrderRequest::at('persons.'.$item->input->personRef, 'guardian_required', 'A minor needs the details of a legal guardian.');
                }
            }
        }
    }

    /**
     * @param list<ResolvedDecision>   $decisions
     * @param array<string, PersonId>  $personIds   by personRef
     * @param array<string, PersonId>  $guardianIds by the personRef of the child
     *
     * @return list<ConfirmationToRecord>
     */
    private function toRecord(array $decisions, array $personIds, array $guardianIds, OrderId $order, OrganizerId $organizer, Orderer $orderer, \DateTimeImmutable $now): array
    {
        $records = [];
        $ordererPerson = null;
        foreach ($decisions as $decision) {
            $item = $decision->item;
            if (null === $item) {
                // An order-level decision needs a subject: the orderer becomes a person of the order.
                if (null === $ordererPerson) {
                    $ordererPerson = PersonId::generate();
                    $this->persons->add(new Person($ordererPerson, $organizer, $order, $orderer->firstName, $orderer->lastName, null, $orderer->email, $orderer->phone));
                }
                $records[] = new ConfirmationToRecord($decision->input->requirementId, $decision->input->versionId, $decision->input->decision, $ordererPerson, null, null, $order, ConfirmationChannel::Web, null, $now);
                continue;
            }

            $subject = $personIds[$item->input->personRef];
            $guardian = Confirmer::Guardian === $decision->requirement->confirmer ? ($guardianIds[$item->input->personRef] ?? null) : null;
            $records[] = new ConfirmationToRecord($decision->input->requirementId, $decision->input->versionId, $decision->input->decision, $subject, $guardian, $item->participationId, $order, ConfirmationChannel::Web, null, $now);
        }

        return $records;
    }
}
