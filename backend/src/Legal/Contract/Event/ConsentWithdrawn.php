<?php

declare(strict_types=1);

namespace Eventor\Legal\Contract\Event;

use Eventor\Legal\Contract\RequirementType;
use Eventor\Shared\Domain\Id\ConfirmationId;
use Eventor\Shared\Domain\Id\LegalRequirementId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;

/**
 * A revocable consent was withdrawn (S16, LEG-09). The registration stays valid. For `likeness` Attention also opens a media review task.
 *
 * P0. Planned subscribers: attention, communication.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'legal.consent_withdrawn', version: 1)]
#[PendingSubscribers('attention', 'communication')]
final readonly class ConsentWithdrawn
{
    public function __construct(
        public OrganizerId $organizerId,
        public ConfirmationId $confirmationId,
        public LegalRequirementId $requirementId,
        public RequirementType $requirementType,
        public ?ParticipationId $participationId,
        public OrderId $orderId,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
