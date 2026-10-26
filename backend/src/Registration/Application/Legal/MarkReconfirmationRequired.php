<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Legal;

use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * `legal.reconfirmation_requested` (S15): a new version of a document asks the dependent participations to confirm it
 * again. Their `legal_state` becomes `reconfirmation_required`; silence is not consent, so nothing else changes.
 */
final readonly class MarkReconfirmationRequired
{
    /**
     * @param list<ParticipationId> $participationIds
     * @param list<OrderId>         $orderIds
     */
    public function __construct(
        public OrganizerId $organizerId,
        public array $participationIds,
        public array $orderIds,
    ) {}
}
