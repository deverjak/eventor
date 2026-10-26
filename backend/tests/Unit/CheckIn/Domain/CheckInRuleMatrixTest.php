<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\CheckIn\Domain;

use Eventor\CheckIn\Contract\CheckInSituation;
use Eventor\CheckIn\Domain\CheckInDecision;
use Eventor\CheckIn\Domain\CheckInFacts;
use Eventor\CheckIn\Domain\CheckInRuleMatrix;
use Eventor\CheckIn\Domain\ParticipationStanding;
use Eventor\CheckIn\Domain\Rule\AlreadyCheckedInRule;
use Eventor\CheckIn\Domain\Rule\CancelledOrExpiredRule;
use Eventor\CheckIn\Domain\Rule\IncompleteRegistrationRule;
use Eventor\CheckIn\Domain\Rule\MissingConfirmationRule;
use Eventor\CheckIn\Domain\Rule\RequiresAttentionRule;
use Eventor\CheckIn\Domain\Rule\UnpaidRule;
use Eventor\CheckIn\Domain\Rule\WrongActivityRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * CHK-09: the exception matrix. The rules are listed in the order of severity, exactly as the module wires them.
 */
final class CheckInRuleMatrixTest extends TestCase
{
    private CheckInRuleMatrix $matrix;

    protected function setUp(): void
    {
        $this->matrix = new CheckInRuleMatrix([
            new AlreadyCheckedInRule(),
            new CancelledOrExpiredRule(),
            new WrongActivityRule(),
            new RequiresAttentionRule(),
            new IncompleteRegistrationRule(),
            new MissingConfirmationRule(),
            new UnpaidRule(),
        ]);
    }

    /**
     * @return iterable<string, array{CheckInFacts, CheckInDecision, ?CheckInSituation}>
     */
    public static function situations(): iterable
    {
        yield 'a clean participation checks in' => [CheckInFacts::clean(), CheckInDecision::Allow, null];
        yield 'a pending but paid participation checks in' => [new CheckInFacts(false, true, ParticipationStanding::Pending, true, true, true), CheckInDecision::Allow, null];
        yield 'already checked in is refused' => [new CheckInFacts(true, true, ParticipationStanding::Confirmed, true, true, true), CheckInDecision::Block, CheckInSituation::AlreadyCheckedIn];
        yield 'cancelled is refused' => [new CheckInFacts(false, true, ParticipationStanding::Inactive, true, true, true), CheckInDecision::Block, CheckInSituation::CancelledOrExpired];
        yield 'another activity is refused' => [new CheckInFacts(false, false, ParticipationStanding::Confirmed, true, true, true), CheckInDecision::Block, CheckInSituation::WrongActivity];
        yield 'requires attention is escalated' => [new CheckInFacts(false, true, ParticipationStanding::RequiresAttention, true, true, true), CheckInDecision::Escalate, CheckInSituation::RequiresAttention];
        yield 'incomplete answers are escalated' => [new CheckInFacts(false, true, ParticipationStanding::Confirmed, false, true, true), CheckInDecision::Escalate, CheckInSituation::RequiresAttention];
        yield 'a missing confirmation is escalated' => [new CheckInFacts(false, true, ParticipationStanding::Confirmed, true, false, true), CheckInDecision::Escalate, CheckInSituation::MissingConfirmation];
        yield 'unpaid is a warning with the cash option' => [new CheckInFacts(false, true, ParticipationStanding::Confirmed, true, true, false), CheckInDecision::Warn, CheckInSituation::Unpaid];
        yield 'pending and unpaid is a warning' => [new CheckInFacts(false, true, ParticipationStanding::Pending, true, true, false), CheckInDecision::Warn, CheckInSituation::Unpaid];
    }

    #[DataProvider('situations')]
    public function test_each_row_of_the_matrix(CheckInFacts $facts, CheckInDecision $decision, ?CheckInSituation $situation): void
    {
        $verdict = $this->matrix->judge($facts);

        self::assertSame($decision, $verdict->decision);
        self::assertSame($situation, $verdict->situation);
    }

    /**
     * @return iterable<string, array{CheckInFacts, CheckInDecision, CheckInSituation}>
     */
    public static function combinations(): iterable
    {
        yield 'cancelled beats unpaid' => [new CheckInFacts(false, true, ParticipationStanding::Inactive, true, true, false), CheckInDecision::Block, CheckInSituation::CancelledOrExpired];
        yield 'already checked in beats everything' => [new CheckInFacts(true, false, ParticipationStanding::Inactive, false, false, false), CheckInDecision::Block, CheckInSituation::AlreadyCheckedIn];
        yield 'the wrong day beats a missing confirmation' => [new CheckInFacts(false, false, ParticipationStanding::Confirmed, true, false, true), CheckInDecision::Block, CheckInSituation::WrongActivity];
        yield 'a missing confirmation beats unpaid' => [new CheckInFacts(false, true, ParticipationStanding::Confirmed, true, false, false), CheckInDecision::Escalate, CheckInSituation::MissingConfirmation];
        yield 'requires attention beats a missing confirmation' => [new CheckInFacts(false, true, ParticipationStanding::RequiresAttention, true, false, false), CheckInDecision::Escalate, CheckInSituation::RequiresAttention];
    }

    #[DataProvider('combinations')]
    public function test_the_most_severe_row_decides(CheckInFacts $facts, CheckInDecision $decision, CheckInSituation $situation): void
    {
        $verdict = $this->matrix->judge($facts);

        self::assertSame($decision, $verdict->decision);
        self::assertSame($situation, $verdict->situation);
    }

    public function test_the_parents_of_the_children_racing_on_another_day_are_not_admitted_to_todays_list(): void
    {
        // The parent registered for the children's activity; the desk is the list of the parents' activity (chapter 04, path 5).
        $verdict = $this->matrix->judge(new CheckInFacts(false, false, ParticipationStanding::Confirmed, true, true, true));

        self::assertSame(CheckInDecision::Block, $verdict->decision);
        self::assertSame(CheckInSituation::WrongActivity, $verdict->situation);
    }

    public function test_without_rules_everybody_may_check_in(): void
    {
        self::assertSame(CheckInDecision::Allow, (new CheckInRuleMatrix([]))->judge(new CheckInFacts(true, false, ParticipationStanding::Inactive, false, false, false))->decision);
    }
}
