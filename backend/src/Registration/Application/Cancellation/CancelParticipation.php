<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Cancellation;

use Eventor\Registration\Contract\CancelledBy;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * REG-09 (the participant, through self-service) and REG-14 (the organizer, with a mandatory reason). `onlyOrder`
 * confines a participant to their own order: a participation of another order is "not found".
 */
final readonly class CancelParticipation
{
    public function __construct(
        public OrganizerId $organizerId,
        public ParticipationId $participationId,
        public CancelledBy $by,
        public ?string $reason = null,
        public ?OrderId $onlyOrder = null,
    ) {}
}
