<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract\Event;

use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Id\VariantId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;

/**
 * The variant (and maybe the activity) of a participation changed in place (S18).
 *
 * P1. Planned subscribers: communication, checkin.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'registration.participation_variant_changed', version: 1)]
final readonly class ParticipationVariantChanged
{
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public ParticipationId $participationId,
        public VariantId $previousVariantId,
        public VariantId $newVariantId,
        public ActivityId $previousActivityId,
        public ActivityId $newActivityId,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
