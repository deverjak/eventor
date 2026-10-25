<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Eventor\Shared\Domain\Id\WaitlistEntryId;

final class WaitlistEntryIdType extends AbstractIdType
{
    public const string NAME = 'waitlist_entry_id';

    protected function idClass(): string
    {
        return WaitlistEntryId::class;
    }
}
