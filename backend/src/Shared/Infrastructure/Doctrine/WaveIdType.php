<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Eventor\Shared\Domain\Id\WaveId;

final class WaveIdType extends AbstractIdType
{
    public const string NAME = 'wave_id';

    protected function idClass(): string
    {
        return WaveId::class;
    }
}
