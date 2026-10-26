<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Legal;

use Eventor\Legal\Contract\ConsentQuery;
use Eventor\Legal\Contract\MissingConfirmation;
use Eventor\Registration\Contract\LegalState;
use Eventor\Registration\Contract\ParticipationState;
use Eventor\Registration\Domain\Participation\Participation;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * `participation.legal_state` (chapter 05, a dimension of its own): derived from what Legal says a participation still
 * owes. A mandatory requirement not granted for the current version makes it `incomplete`, or
 * `reconfirmation_required` when it was granted for an older version (S15); optional ones do not count. The lifecycle
 * `state` is not touched: an unpaid participation with a missing confirmation keeps its reservation.
 */
final readonly class LegalStateRecalculator
{
    public function __construct(private ConsentQuery $consents) {}

    /**
     * @param list<Participation> $participations
     */
    public function recompute(OrganizerId $organizer, array $participations): void
    {
        $relevant = array_values(array_filter($participations, static fn(Participation $p): bool => ParticipationState::Cancelled !== $p->state() && ParticipationState::Transferred !== $p->state()));
        if ([] === $relevant) {
            return;
        }

        $missing = $this->consents->missingFor($organizer, array_map(static fn(Participation $p): ParticipationId => $p->id(), $relevant));
        foreach ($relevant as $participation) {
            $mandatory = array_values(array_filter(
                $missing[$participation->id()->toString()] ?? [],
                static fn(MissingConfirmation $m): bool => $m->mandatory,
            ));
            $participation->changeLegalState(match (true) {
                [] === $mandatory => LegalState::Complete,
                [] !== array_filter($mandatory, static fn(MissingConfirmation $m): bool => $m->reconfirmation) => LegalState::ReconfirmationRequired,
                default => LegalState::Incomplete,
            });
        }
    }
}
