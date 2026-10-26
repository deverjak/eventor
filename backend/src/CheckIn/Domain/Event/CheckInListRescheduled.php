<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Domain\Event;

use Eventor\Shared\Domain\Id\CheckInListId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Domain event: the list opens at another time (reaction to `catalog.activity_rescheduled`).
 */
final readonly class CheckInListRescheduled
{
    public function __construct(
        public OrganizerId $organizerId,
        public CheckInListId $listId,
        public ?\DateTimeImmutable $previousOpensAt,
        public ?\DateTimeImmutable $opensAt,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
