<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Program;

use Eventor\Catalog\Contract\Exception\ProgramItemNotFound;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\ProgramItemId;

interface ProgramItemRepository
{
    /**
     * @throws ProgramItemNotFound
     */
    public function get(EventId $event, ProgramItemId $id): ProgramItem;

    public function add(ProgramItem $item): void;

    public function remove(ProgramItem $item): void;
}
