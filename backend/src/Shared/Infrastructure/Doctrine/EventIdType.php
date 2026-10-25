<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Eventor\Shared\Domain\Id\EventId;

final class EventIdType extends AbstractIdType
{
    public const string NAME = 'event_id';

    protected function idClass(): string
    {
        return EventId::class;
    }
}
