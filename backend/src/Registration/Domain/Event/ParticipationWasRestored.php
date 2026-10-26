<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Event;

use Eventor\Registration\Contract\RestoredBy;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * Domain event: an expired or flagged participation was confirmed again (CAP-05, S06). Published as
 * `registration.participation_restored`.
 */
final readonly class ParticipationWasRestored
{
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public ParticipationId $participationId,
        public RestoredBy $by,
    ) {}
}
