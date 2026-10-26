<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Application\Desk;

use Eventor\CheckIn\Domain\CheckInFacts;
use Eventor\CheckIn\Domain\CheckInRecordRepository;
use Eventor\CheckIn\Domain\CheckInRuleMatrix;
use Eventor\CheckIn\Domain\ParticipationStanding;
use Eventor\Registration\Contract\AnswersState;
use Eventor\Registration\Contract\Exception\ParticipationNotFound;
use Eventor\Registration\Contract\LegalState;
use Eventor\Registration\Contract\MissingConfirmationView;
use Eventor\Registration\Contract\ParticipationCheckInView;
use Eventor\Registration\Contract\ParticipationQuery;
use Eventor\Registration\Contract\ParticipationState;
use Eventor\Shared\Domain\Finance\FinancialState;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * Judges an arrival (CHK-09): reads the check-in projection of Registration (with the finance state of Payments), turns
 * it into the plain facts of the domain and asks the rule matrix.
 */
final readonly class CheckInAssessor
{
    public function __construct(
        private ParticipationQuery $participations,
        private CheckInRecordRepository $records,
        private CheckInRuleMatrix $matrix,
    ) {}

    /**
     * @throws ParticipationNotFound
     */
    public function assess(OrganizerId $organizer, ActivityId $listActivity, ParticipationId $participation): Assessment
    {
        $view = $this->participations->forCheckIn($organizer, $participation);
        $existing = $this->records->validFor($participation);

        return new Assessment($view, $this->matrix->judge(self::factsOf($view, $listActivity, null !== $existing)), $existing);
    }

    public static function factsOf(ParticipationCheckInView $view, ActivityId $listActivity, bool $alreadyCheckedIn): CheckInFacts
    {
        return new CheckInFacts(
            $alreadyCheckedIn,
            $view->activityId->equals($listActivity),
            self::standingOf($view->state),
            AnswersState::Complete === $view->answersState,
            LegalState::Complete === $view->legalState && [] === array_filter($view->missingConfirmations, static fn(MissingConfirmationView $missing): bool => $missing->mandatory),
            \in_array($view->finance->state, [FinancialState::Paid, FinancialState::Overpaid, FinancialState::NotRequired], true),
        );
    }

    private static function standingOf(ParticipationState $state): ParticipationStanding
    {
        return match ($state) {
            ParticipationState::Confirmed => ParticipationStanding::Confirmed,
            ParticipationState::Pending => ParticipationStanding::Pending,
            ParticipationState::RequiresAttention => ParticipationStanding::RequiresAttention,
            ParticipationState::Expired, ParticipationState::Cancelled, ParticipationState::Transferred => ParticipationStanding::Inactive,
        };
    }
}
