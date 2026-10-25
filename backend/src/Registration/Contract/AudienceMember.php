<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract;

use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * One delivery target: an orderer for one activity. No contact data; read it at send time with `forOrder()`.
 */
final readonly class AudienceMember
{
    /**
     * @param list<ParticipationId> $participationIds
     */
    public function __construct(
        public OrderId $orderId,
        public ActivityId $activityId,
        public array $participationIds,
    ) {}
}
