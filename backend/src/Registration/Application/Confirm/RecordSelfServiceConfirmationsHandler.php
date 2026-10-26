<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Confirm;

use Eventor\Catalog\Contract\EventCatalog;
use Eventor\Legal\Contract\ApplicableRequirement;
use Eventor\Legal\Contract\ConfirmationChannel;
use Eventor\Legal\Contract\ConfirmationRecorder;
use Eventor\Legal\Contract\ConfirmationToRecord;
use Eventor\Legal\Contract\Confirmer;
use Eventor\Legal\Contract\RegistrationSlot;
use Eventor\Legal\Contract\RequirementResolver;
use Eventor\Registration\Contract\Exception\InvalidOrderRequest;
use Eventor\Registration\Contract\Exception\ParticipationNotFound;
use Eventor\Registration\Domain\Eligibility\ParticipationEligibilityPolicy;
use Eventor\Registration\Domain\Order\OrderRepository;
use Eventor\Registration\Domain\Participation\Participation;
use Eventor\Registration\Domain\Participation\ParticipationRepository;
use Eventor\Registration\Domain\Person\PersonRepository;
use Eventor\Shared\Domain\Id\LegalRequirementId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Records the decisions of the participant in Legal's ledger inside this transaction (`channel = web`, `orderId` always
 * set). The requirement must apply to the participation now; its current document version is the one decided on.
 * Registration's `legal_state` follows from `legal.confirmation_recorded`.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class RecordSelfServiceConfirmationsHandler
{
    public function __construct(
        private OrderRepository $orders,
        private ParticipationRepository $participations,
        private PersonRepository $persons,
        private EventCatalog $catalog,
        private RequirementResolver $requirements,
        private ConfirmationRecorder $recorder,
        private ClockInterface $clock,
    ) {}

    public function __invoke(RecordSelfServiceConfirmations $command): void
    {
        if ([] === $command->decisions) {
            throw InvalidOrderRequest::at('decisions', 'required', 'At least one decision is needed.');
        }

        $order = $this->orders->get($command->orderId);
        $offer = $this->catalog->registrationSnapshot($command->organizerId, $order->eventId());
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        /** @var array<string, Participation> $mine */
        $mine = [];
        foreach ($this->participations->ofOrder($command->orderId) as $participation) {
            if ($participation->isActive()) {
                $mine[$participation->id()->toString()] = $participation;
            }
        }
        $people = [];
        foreach ($this->persons->ofOrder($command->orderId) as $person) {
            $people[$person->id()->toString()] = $person;
        }

        $slots = array_values(array_map(
            fn(Participation $p): RegistrationSlot => new RegistrationSlot(
                $p->id()->toString(),
                $p->activityId(),
                $p->variantId(),
                ParticipationEligibilityPolicy::isMinor($people[$p->personId()->toString()]?->birthDate(), $offer->ageReferenceDate),
            ),
            $mine,
        ));
        $required = $this->requirements->forRegistration($command->organizerId, $order->eventId(), $slots);

        $records = [];
        $violations = [];
        foreach ($command->decisions as $i => $decision) {
            $participation = null;
            if (null !== $decision['participationId']) {
                $participation = $mine[$decision['participationId']->toString()] ?? throw ParticipationNotFound::forId($decision['participationId']);
            }

            $candidates = null === $participation ? $required->orderLevel : ($required->perSlot[$participation->id()->toString()] ?? []);
            $requirement = $this->find($candidates, $decision['requirementId']);
            if (null === $requirement) {
                $violations[] = ['path' => 'decisions.'.$i.'.requirementId', 'message' => 'This requirement does not apply here.', 'code' => 'requirement_not_applicable'];
                continue;
            }

            if (null === $participation) {
                $subject = array_values($people)[0]->id();
                $records[] = new ConfirmationToRecord($requirement->requirementId, $requirement->versionId, $decision['decision'], $subject, null, null, $command->orderId, ConfirmationChannel::Web, null, $now);
                continue;
            }

            $subjectPerson = $people[$participation->personId()->toString()];
            $guardian = Confirmer::Guardian === $requirement->confirmer ? $subjectPerson->guardianPersonId() : null;
            $records[] = new ConfirmationToRecord($requirement->requirementId, $requirement->versionId, $decision['decision'], $subjectPerson->id(), $guardian, $participation->id(), $command->orderId, ConfirmationChannel::Web, null, $now);
        }

        if ([] !== $violations) {
            throw new InvalidOrderRequest($violations);
        }
        if ([] !== $records) {
            $this->recorder->record($command->organizerId, $records);
            // The order records that it was touched, so the command leaves a domain event of its own (audit by Legal's ledger).
            $order->noteDataCorrected('confirmation', $command->orderId->toString(), array_map(static fn(ConfirmationToRecord $r): string => $r->requirementId->toString(), $records));
        }
    }

    /**
     * @param list<ApplicableRequirement> $candidates
     */
    private function find(array $candidates, LegalRequirementId $id): ?ApplicableRequirement
    {
        foreach ($candidates as $candidate) {
            if ($candidate->requirementId->equals($id)) {
                return $candidate;
            }
        }

        return null;
    }
}
