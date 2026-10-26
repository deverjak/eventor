<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Event;

use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * Domain event: money arrived for expired participations (CAP-05, S05). Published as `registration.late_payment_received`.
 */
final readonly class LatePaymentWasReceived
{
    /**
     * @param list<ParticipationId> $participationIds
     */
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public array $participationIds,
        public bool $capacityAvailable,
        public bool $autoRestore,
    ) {}
}
