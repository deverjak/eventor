<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract\Event;

use Eventor\Shared\Domain\Id\FormVersionId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;

/**
 * Participations lack answers to required fields of the current form version (S17); the participant is asked to complete them.
 *
 * P0. Planned subscribers: communication.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'registration.answers_incomplete', version: 1)]
final readonly class AnswersIncomplete
{
    /**
     * @param list<ParticipationId> $participationIds
     */
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public array $participationIds,
        public FormVersionId $formVersionId,
        public \DateTimeImmutable $occurredAt,
    ) {
        if ([] === $participationIds) {
            throw new \InvalidArgumentException('At least one participation is affected.');
        }
    }
}
