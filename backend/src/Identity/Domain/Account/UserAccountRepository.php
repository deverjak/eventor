<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\Account;

interface UserAccountRepository
{
    public function findByEmail(string $email): ?UserAccount;

    public function add(UserAccount $account): void;
}
