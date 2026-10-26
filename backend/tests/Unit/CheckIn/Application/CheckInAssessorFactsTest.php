<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\CheckIn\Application;

use Eventor\CheckIn\Application\Desk\CheckInAssessor;
use Eventor\CheckIn\Domain\ParticipationStanding;
use Eventor\Registration\Contract\AnswersState;
use Eventor\Registration\Contract\LegalState;
use Eventor\Registration\Contract\MissingConfirmationView;
use Eventor\Registration\Contract\ParticipationCheckInView;
use Eventor\Registration\Contract\ParticipationState;
use Eventor\Shared\Domain\Finance\FinancialState;
use Eventor\Shared\Domain\Finance\FinancialStateView;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\LegalRequirementId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Id\PersonId;
use Eventor\Shared\Domain\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The translation of the Registration / Payments projection into the plain facts the rule matrix judges.
 */
final class CheckInAssessorFactsTest extends TestCase
{
    private ActivityId $activity;

    protected function setUp(): void
    {
        $this->activity = ActivityId::generate();
    }

    /**
     * @param list<MissingConfirmationView> $missing
     */
    private function view(
        ParticipationState $state = ParticipationState::Confirmed,
        LegalState $legal = LegalState::Complete,
        AnswersState $answers = AnswersState::Complete,
        FinancialState $finance = FinancialState::Paid,
        array $missing = [],
        ?ActivityId $activity = null,
    ): ParticipationCheckInView {
        return new ParticipationCheckInView(
            ParticipationId::generate(),
            OrderId::generate(),
            PersonId::generate(),
            'Jana Nováková',
            'AB12CD34EF',
            $activity ?? $this->activity,
            'Trail 5 km',
            'Jednotlivec',
            null,
            null,
            $state,
            $legal,
            $answers,
            new FinancialStateView($finance, Money::of(30000), Money::of(FinancialState::Paid === $finance ? 30000 : 0)),
            $missing,
            null,
            [],
        );
    }

    /**
     * @return iterable<string, array{ParticipationState, ParticipationStanding}>
     */
    public static function states(): iterable
    {
        yield 'confirmed' => [ParticipationState::Confirmed, ParticipationStanding::Confirmed];
        yield 'pending' => [ParticipationState::Pending, ParticipationStanding::Pending];
        yield 'requires attention' => [ParticipationState::RequiresAttention, ParticipationStanding::RequiresAttention];
        yield 'cancelled' => [ParticipationState::Cancelled, ParticipationStanding::Inactive];
        yield 'expired' => [ParticipationState::Expired, ParticipationStanding::Inactive];
        yield 'transferred' => [ParticipationState::Transferred, ParticipationStanding::Inactive];
    }

    #[DataProvider('states')]
    public function test_the_participation_state_becomes_the_standing(ParticipationState $state, ParticipationStanding $standing): void
    {
        self::assertSame($standing, CheckInAssessor::factsOf($this->view($state), $this->activity, false)->standing);
    }

    public function test_a_clean_participation_on_its_own_list_has_clean_facts(): void
    {
        $facts = CheckInAssessor::factsOf($this->view(), $this->activity, false);

        self::assertTrue($facts->sameActivity);
        self::assertTrue($facts->answersComplete);
        self::assertTrue($facts->confirmationsComplete);
        self::assertTrue($facts->paid);
        self::assertFalse($facts->alreadyCheckedIn);
    }

    public function test_the_list_of_another_activity_is_a_different_activity(): void
    {
        self::assertFalse(CheckInAssessor::factsOf($this->view(), ActivityId::generate(), false)->sameActivity);
    }

    public function test_an_existing_check_in_is_carried_over(): void
    {
        self::assertTrue(CheckInAssessor::factsOf($this->view(), $this->activity, true)->alreadyCheckedIn);
    }

    public function test_incomplete_answers_are_incomplete_answers(): void
    {
        self::assertFalse(CheckInAssessor::factsOf($this->view(answers: AnswersState::Incomplete), $this->activity, false)->answersComplete);
    }

    public function test_confirmations_are_complete_only_when_the_ledger_is_complete_and_nothing_mandatory_is_missing(): void
    {
        $mandatory = new MissingConfirmationView(LegalRequirementId::generate(), 'Health declaration', true);
        $optional = new MissingConfirmationView(LegalRequirementId::generate(), 'Newsletter', false);

        self::assertFalse(CheckInAssessor::factsOf($this->view(legal: LegalState::Incomplete), $this->activity, false)->confirmationsComplete);
        self::assertFalse(CheckInAssessor::factsOf($this->view(legal: LegalState::ReconfirmationRequired), $this->activity, false)->confirmationsComplete);
        self::assertFalse(CheckInAssessor::factsOf($this->view(missing: [$mandatory]), $this->activity, false)->confirmationsComplete);
        self::assertTrue(CheckInAssessor::factsOf($this->view(missing: [$optional]), $this->activity, false)->confirmationsComplete);
    }

    /**
     * @return iterable<string, array{FinancialState, bool}>
     */
    public static function finance(): iterable
    {
        yield 'paid' => [FinancialState::Paid, true];
        yield 'overpaid' => [FinancialState::Overpaid, true];
        yield 'nothing to pay' => [FinancialState::NotRequired, true];
        yield 'unpaid' => [FinancialState::Unpaid, false];
        yield 'partially paid' => [FinancialState::PartiallyPaid, false];
        yield 'disputed' => [FinancialState::Disputed, false];
        yield 'refunded' => [FinancialState::Refunded, false];
    }

    #[DataProvider('finance')]
    public function test_only_a_settled_order_counts_as_paid(FinancialState $state, bool $paid): void
    {
        self::assertSame($paid, CheckInAssessor::factsOf($this->view(finance: $state), $this->activity, false)->paid);
    }
}
