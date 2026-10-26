<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Query\View;

use Eventor\Registration\Contract\AnswersState;
use Eventor\Registration\Contract\LegalState;
use Eventor\Registration\Contract\ParticipationState;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Id\PersonId;
use Eventor\Shared\Domain\Id\VariantId;
use Eventor\Shared\Domain\Money;

/**
 * One participation in the self-service page. `qrToken` is null once the participation cannot be checked in
 * (cancelled, expired, transferred): the code would be refused anyway.
 */
final readonly class SelfServiceParticipationView
{
    /**
     * @param list<MissingConfirmationItem> $missingConfirmations
     */
    public function __construct(
        public ParticipationId $id,
        public PersonId $personId,
        public string $personName,
        public ActivityId $activityId,
        public string $activityName,
        public ?\DateTimeImmutable $activityStartsAt,
        public VariantId $variantId,
        public string $variantName,
        public ParticipationState $state,
        public LegalState $legalState,
        public AnswersState $answersState,
        public ?\DateTimeImmutable $holdExpiresAt,
        public string $registrationCode,
        public Money $price,
        public ?string $qrToken,
        public array $missingConfirmations,
        public bool $canCancel,
    ) {}
}
