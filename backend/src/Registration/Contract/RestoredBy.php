<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract;

/**
 * Who restored a participation: the system (auto-restore on late payment) or a team member.
 */
enum RestoredBy: string
{
    case System = 'system';
    case User = 'user';
}
