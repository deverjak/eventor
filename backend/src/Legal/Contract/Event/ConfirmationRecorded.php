<?php

declare(strict_types=1);

namespace Eventor\Legal\Contract\Event;

use Eventor\Legal\Contract\ConfirmationChannel;
use Eventor\Legal\Contract\ConsentDecision;
use Eventor\Shared\Domain\Id\ConfirmationId;
use Eventor\Shared\Domain\Id\DocumentVersionId;
use Eventor\Shared\Domain\Id\LegalRequirementId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;

/**
 * A decision (requested, granted, declined, withdrawn) was appended to the confirmation ledger (LEG-07). Registration recomputes `legal_state` of the participation.
 *
 * P0. Planned subscribers: registration.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'legal.confirmation_recorded', version: 1)]
final readonly class ConfirmationRecorded
{
    public function __construct(
        public OrganizerId $organizerId,
        public ConfirmationId $confirmationId,
        public LegalRequirementId $requirementId,
        public DocumentVersionId $versionId,
        public ConsentDecision $decision,
        public ?ParticipationId $participationId,
        public ?OrderId $orderId,
        public ConfirmationChannel $channel,
        public \DateTimeImmutable $occurredAt,
    ) {
        if (null === $participationId && null === $orderId) {
            throw new \InvalidArgumentException('A confirmation belongs to a participation or an order.');
        }
    }
}
