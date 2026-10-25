<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract;

use Eventor\Catalog\Contract\Exception\EventNotFound;
use Eventor\Catalog\Contract\Exception\ProgramItemNotFound;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ProgramItemId;

/**
 * The program of an event (PRG-01..04): the public page, the participants' view and change notifications.
 */
interface ProgramReader
{
    /**
     * Items visible to the audience, ordered by start.
     *
     * @return list<ProgramItemView>
     *
     * @throws EventNotFound
     */
    public function forEvent(OrganizerId $organizer, EventId $event, ProgramVisibility $audience): array;

    /**
     * @throws ProgramItemNotFound
     */
    public function item(OrganizerId $organizer, ProgramItemId $item): ProgramItemView;
}
