<?php

declare(strict_types=1);

namespace Eventor\Identity\Infrastructure\Doctrine;

use Eventor\Identity\Domain\Account\UserAccountId;
use Eventor\Shared\Infrastructure\Doctrine\AbstractIdType;

final class UserAccountIdType extends AbstractIdType
{
    public const string NAME = 'user_account_id';

    protected function idClass(): string
    {
        return UserAccountId::class;
    }
}
