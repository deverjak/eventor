<?php

declare(strict_types=1);

namespace Eventor\Communication\Contract;

use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\VariantId;

/**
 * Audience as other modules describe it. `activeOnly` = participations that still count (pending, confirmed, requires attention);
 * Communication maps it to Registration's `AudienceCriteria`. Empty lists = no restriction.
 */
final readonly class CampaignAudience
{
    /**
     * @param list<ActivityId> $activityIds
     * @param list<VariantId> $variantIds
     */
    public function __construct(
        public EventId $eventId,
        public array $activityIds,
        public array $variantIds,
        public bool $activeOnly,
        /** true = only checked-in participants (e.g. the closing message, POST-05), false = only those not checked in */
        public ?bool $checkedIn = null,
    ) {}
}
