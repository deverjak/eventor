<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Application\Query\View;

use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * The detail before the confirmation (CHK-04) and the verdict of the exception matrix (CHK-09). No birth date, no
 * contacts, no form answers: the check-in projection only.
 */
final readonly class DeskParticipantView
{
    /**
     * @param list<DeskMissingConfirmationView> $missingConfirmations
     * @param list<DeskOtherParticipationView>  $otherParticipations
     */
    public function __construct(
        public ParticipationId $participationId,
        public string $displayName,
        public string $registrationCode,
        public ActivityId $activityId,
        public string $activityName,
        public string $variantName,
        public ?string $waveName,
        public ?string $categoryCode,
        public string $state,
        public string $financialState,
        public int $amountDueMinor,
        public int $receivedMinor,
        public array $missingConfirmations,
        public ?string $checkInNote,
        public array $otherParticipations,
        public string $decision,
        public ?string $situation,
        public ?DeskCheckInView $checkIn,
        public ?DeskCashOfferView $cash,
    ) {}
}
