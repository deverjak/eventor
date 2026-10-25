<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\Port;

interface PasswordHasher
{
    public function hash(#[\SensitiveParameter] string $plainPassword): string;
}
