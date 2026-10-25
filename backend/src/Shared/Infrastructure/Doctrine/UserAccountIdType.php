<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Eventor\Shared\Domain\Id\UserAccountId;

final class UserAccountIdType extends AbstractIdType
{
    public const string NAME = 'user_account_id';

    protected function idClass(): string
    {
        return UserAccountId::class;
    }
}
