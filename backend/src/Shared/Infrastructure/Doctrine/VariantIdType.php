<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Eventor\Shared\Domain\Id\VariantId;

final class VariantIdType extends AbstractIdType
{
    public const string NAME = 'variant_id';

    protected function idClass(): string
    {
        return VariantId::class;
    }
}
