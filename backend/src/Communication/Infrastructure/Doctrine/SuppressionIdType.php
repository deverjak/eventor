<?php

declare(strict_types=1);

namespace Eventor\Communication\Infrastructure\Doctrine;

use Eventor\Communication\Domain\Suppression\SuppressionId;
use Eventor\Shared\Infrastructure\Doctrine\AbstractIdType;

final class SuppressionIdType extends AbstractIdType
{
    public const string NAME = 'suppression_id';

    protected function idClass(): string
    {
        return SuppressionId::class;
    }
}
