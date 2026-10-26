<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract\Event;

use Eventor\Registration\Contract\CancellationReason;
use Eventor\Registration\Contract\CancelledBy;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;

/**
 * A participation was cancelled (REG-09, REG-14, EVT-12). `reason` is a closed code; the free text stays in Registration (it may contain personal data).
 *
 * P0. Planned subscribers: communication, checkin.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'registration.participation_cancelled', version: 1)]
final readonly class ParticipationCancelled
{
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public ParticipationId $participationId,
        public CancelledBy $by,
        public CancellationReason $reason,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
