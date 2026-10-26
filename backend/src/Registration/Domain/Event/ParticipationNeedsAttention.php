<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Event;

use Eventor\Registration\Contract\AttentionCause;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * Domain event: a participation needs a human (late payment, payment on a cancelled participation). Published as
 * `registration.participation_requires_attention`.
 */
final readonly class ParticipationNeedsAttention
{
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public ParticipationId $participationId,
        public AttentionCause $cause,
    ) {}
}
