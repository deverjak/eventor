<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract;

use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\VariantId;

/**
 * Registration-owned part of a campaign audience (S12). Empty `activityIds` / `variantIds` = no restriction.
 * `states` default to pending + confirmed + requires_attention for operational messages.
 */
final readonly class AudienceCriteria
{
    /**
     * @param list<ActivityId> $activityIds
     * @param list<VariantId> $variantIds
     * @param list<ParticipationState> $states
     */
    public function __construct(
        public EventId $eventId,
        public array $activityIds,
        public array $variantIds,
        public array $states,
    ) {}
}
