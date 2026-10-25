<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Eventor\Shared\Domain\Id\ParticipationId;

final class ParticipationIdType extends AbstractIdType
{
    public const string NAME = 'participation_id';

    protected function idClass(): string
    {
        return ParticipationId::class;
    }
}
