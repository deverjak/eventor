<?php

declare(strict_types=1);

namespace Eventor\Legal\Contract\Event;

use Eventor\Shared\Domain\Id\DocumentVersionId;
use Eventor\Shared\Domain\Id\LegalRequirementId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;

/**
 * Participations and orders that confirmed an older version must confirm the new one (S15). Their `legal_state` becomes reconfirmation_required.
 *
 * P0. Planned subscribers: communication, registration.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'legal.reconfirmation_requested', version: 1)]
#[PendingSubscribers('communication', 'registration')]
final readonly class ReconfirmationRequested
{
    /**
     * @param list<ParticipationId> $participationIds
     * @param list<OrderId> $orderIds
     */
    public function __construct(
        public OrganizerId $organizerId,
        public LegalRequirementId $requirementId,
        public DocumentVersionId $versionId,
        public array $participationIds,
        public array $orderIds,
        public \DateTimeImmutable $occurredAt,
    ) {
        if ([] === $participationIds && [] === $orderIds) {
            throw new \InvalidArgumentException('A reconfirmation request must concern at least one participation or order.');
        }
    }
}
