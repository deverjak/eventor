<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Restore;

use Eventor\Registration\Contract\RestoredBy;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * CAP-05: put an expired or flagged participation back among the confirmed ones, when the capacity allows. A team member
 * needs a reason (AUD-02); the system restores on a late payment (S06) without one.
 */
final readonly class RestoreParticipation
{
    public function __construct(
        public OrganizerId $organizerId,
        public ParticipationId $participationId,
        public RestoredBy $by,
        public ?string $reason = null,
    ) {}
}
