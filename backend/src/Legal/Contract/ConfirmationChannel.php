<?php

declare(strict_types=1);

namespace Eventor\Legal\Contract;

/**
 * Where a decision was made: the public form / self-service, or on site by staff with a note (LEG-07).
 */
enum ConfirmationChannel: string
{
    case Web = 'web';
    case Staff = 'staff';
}
