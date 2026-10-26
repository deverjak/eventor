<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Legal;

use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * `legal.confirmation_recorded`: recompute the legal state of the participation the decision is about, or of every
 * participation of the order for an order-level decision. A repeated command computes the same state.
 */
final readonly class RecomputeLegalState
{
    public function __construct(
        public OrganizerId $organizerId,
        public ?ParticipationId $participationId,
        public ?OrderId $orderId,
    ) {}
}
