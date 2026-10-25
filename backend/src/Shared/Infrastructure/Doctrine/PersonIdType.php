<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Eventor\Shared\Domain\Id\PersonId;

final class PersonIdType extends AbstractIdType
{
    public const string NAME = 'person_id';

    protected function idClass(): string
    {
        return PersonId::class;
    }
}
