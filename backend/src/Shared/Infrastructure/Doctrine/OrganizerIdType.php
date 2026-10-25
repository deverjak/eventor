<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Eventor\Shared\Domain\Id\OrganizerId;

final class OrganizerIdType extends AbstractIdType
{
    public const string NAME = 'organizer_id';

    protected function idClass(): string
    {
        return OrganizerId::class;
    }
}
