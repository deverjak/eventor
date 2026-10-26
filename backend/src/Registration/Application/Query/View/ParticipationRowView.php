<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Query\View;

use Eventor\Registration\Contract\AnswersState;
use Eventor\Registration\Contract\LegalState;
use Eventor\Registration\Contract\ParticipationState;
use Eventor\Shared\Domain\Finance\FinancialState;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Id\PersonId;
use Eventor\Shared\Domain\Id\VariantId;
use Eventor\Shared\Domain\Money;

/**
 * A line of the participant list of an event (organizer's view, personal data for roles with `participants.view.full`).
 * The four dimensions stay separate; the UI composes the summary label.
 */
final readonly class ParticipationRowView
{
    public function __construct(
        public ParticipationId $id,
        public OrderId $orderId,
        public EventId $eventId,
        public string $orderNumber,
        public PersonId $personId,
        public string $personName,
        public ?string $email,
        public string $registrationCode,
        public ActivityId $activityId,
        public string $activityName,
        public VariantId $variantId,
        public string $variantName,
        public ParticipationState $state,
        public LegalState $legalState,
        public AnswersState $answersState,
        public ?\DateTimeImmutable $holdExpiresAt,
        public Money $price,
        public ?FinancialState $financialState,
        public \DateTimeImmutable $createdAt,
    ) {}
}
