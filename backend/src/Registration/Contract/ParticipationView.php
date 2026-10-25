<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract;

use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Id\PersonId;
use Eventor\Shared\Domain\Id\VariantId;
use Eventor\Shared\Domain\Id\WaveId;

/**
 * Facts of one participation without personal data. The four dimensions are separate (chapter 05): lifecycle `state`, `legalState`,
 * `answersState`; check-in is derived by CheckIn.
 */
final readonly class ParticipationView
{
    public function __construct(
        public ParticipationId $id,
        public OrderId $orderId,
        public PersonId $personId,
        public EventId $eventId,
        public ActivityId $activityId,
        public VariantId $variantId,
        public ?WaveId $waveId,
        public ParticipationState $state,
        public LegalState $legalState,
        public AnswersState $answersState,
        public ?\DateTimeImmutable $holdExpiresAt,
        public string $registrationCode,
        public ?string $categoryCode,
    ) {}
}
