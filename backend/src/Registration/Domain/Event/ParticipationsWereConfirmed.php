<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Event;

use Eventor\Registration\Contract\ConfirmationReason;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * Domain event: participations of an order became confirmed (payment, cash, free, restore). Published as
 * `registration.participations_confirmed`; the per-participation audit is `ParticipationChanged`.
 */
final readonly class ParticipationsWereConfirmed
{
    /**
     * @param list<ParticipationId> $participationIds
     */
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public array $participationIds,
        public ConfirmationReason $reason,
    ) {}
}
