<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Eventor\Shared\Domain\Id\ConfirmationId;

final class ConfirmationIdType extends AbstractIdType
{
    public const string NAME = 'confirmation_id';

    protected function idClass(): string
    {
        return ConfirmationId::class;
    }
}
