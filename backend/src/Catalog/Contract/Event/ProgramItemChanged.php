<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract\Event;

use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ProgramItemId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;

/**
 * A program item was created, moved or changed (PRG-03). `important` = tell the affected participants; activityId = null means the item is for all participants of the event.
 *
 * P0. Planned subscribers: communication.
 * Note: Written as `catalog.program_item_changed` in the catalogue.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'catalog.program_item_changed', version: 1)]
final readonly class ProgramItemChanged
{
    public function __construct(
        public OrganizerId $organizerId,
        public EventId $eventId,
        public ProgramItemId $programItemId,
        public ?ActivityId $activityId,
        public bool $important,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
