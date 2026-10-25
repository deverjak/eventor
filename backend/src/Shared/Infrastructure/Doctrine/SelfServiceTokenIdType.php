<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Eventor\Shared\Domain\Id\SelfServiceTokenId;

final class SelfServiceTokenIdType extends AbstractIdType
{
    public const string NAME = 'self_service_token_id';

    protected function idClass(): string
    {
        return SelfServiceTokenId::class;
    }
}
