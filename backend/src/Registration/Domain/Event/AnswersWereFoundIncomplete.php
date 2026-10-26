<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Event;

use Eventor\Shared\Domain\Id\FormVersionId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * Domain event: a new required form field left answers missing (S17). Published as `registration.answers_incomplete`.
 */
final readonly class AnswersWereFoundIncomplete
{
    /**
     * @param list<ParticipationId> $participationIds
     */
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public array $participationIds,
        public FormVersionId $formVersionId,
    ) {}
}
