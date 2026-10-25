<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Legal;

use Eventor\Legal\Contract\ConfirmationChannel;
use Eventor\Legal\Contract\ConfirmationToRecord;
use Eventor\Legal\Contract\ConsentDecision;
use Eventor\Legal\Contract\Exception\RequirementNotSatisfied;
use Eventor\Shared\Domain\Id\DocumentVersionId;
use Eventor\Shared\Domain\Id\LegalRequirementId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Id\PersonId;
use Eventor\Shared\UserInterface\Http\Problem\ProblemFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Translator;

final class LegalContractTest extends TestCase
{
    public function test_a_confirmation_belongs_to_a_participation_or_an_order(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->confirmation(participation: null, order: null);
    }

    public function test_a_decision_recorded_by_staff_needs_a_note(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->confirmation(participation: ParticipationId::generate(), channel: ConfirmationChannel::Staff, note: '  ');
    }

    public function test_a_web_decision_needs_no_note_and_a_staff_decision_with_a_note_is_fine(): void
    {
        self::assertSame(ConsentDecision::Granted, $this->confirmation(participation: ParticipationId::generate())->decision);
        self::assertSame('health form signed on paper', $this->confirmation(order: OrderId::generate(), channel: ConfirmationChannel::Staff, note: 'health form signed on paper')->note);
    }

    public function test_missing_confirmations_become_a_422_problem_with_a_violation_per_requirement(): void
    {
        $first = LegalRequirementId::generate();
        $exception = new RequirementNotSatisfied([$first, LegalRequirementId::generate()]);

        $problem = (new ProblemFactory(new Translator('en')))->fromThrowable($exception, null);

        self::assertSame('missing_confirmation', $problem->code);
        self::assertSame(422, $problem->status);
        self::assertSame(['missingCount' => 2], $problem->context);
        self::assertCount(2, $exception->violations());
        self::assertSame('confirmations.'.$first->toString(), $exception->violations()[0]['path']);
    }

    private function confirmation(?ParticipationId $participation = null, ?OrderId $order = null, ConfirmationChannel $channel = ConfirmationChannel::Web, ?string $note = null): ConfirmationToRecord
    {
        return new ConfirmationToRecord(
            LegalRequirementId::generate(),
            DocumentVersionId::generate(),
            ConsentDecision::Granted,
            PersonId::generate(),
            null,
            $participation,
            $order,
            $channel,
            $note,
            new \DateTimeImmutable('2026-11-01T12:00:00+00:00'),
        );
    }
}
