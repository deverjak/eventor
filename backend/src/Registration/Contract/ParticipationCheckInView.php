<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract;

use Eventor\Shared\Domain\Finance\FinancialStateView;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Id\PersonId;

/**
 * The check-in projection (CHK-04). Deliberately without birth date, contacts and form answers; only `checkInNote`
 * and the fields the operator needs to decide (state, finance, missing confirmations).
 */
final readonly class ParticipationCheckInView
{
    /**
     * @param list<MissingConfirmationView> $missingConfirmations
     * @param list<OtherParticipationView> $otherParticipations
     */
    public function __construct(
        public ParticipationId $id,
        public OrderId $orderId,
        public PersonId $personId,
        public string $displayName,
        public string $registrationCode,
        public ActivityId $activityId,
        public string $activityName,
        public string $variantName,
        public ?string $waveName,
        public ?string $categoryCode,
        public ParticipationState $state,
        public LegalState $legalState,
        public AnswersState $answersState,
        public FinancialStateView $finance,
        public array $missingConfirmations,
        public ?string $checkInNote,
        public array $otherParticipations,
    ) {}
}
