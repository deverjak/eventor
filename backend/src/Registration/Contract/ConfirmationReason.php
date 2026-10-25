<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract;

/**
 * Why participations became confirmed (`registration.participations_confirmed`).
 */
enum ConfirmationReason: string
{
    case Paid = 'paid';
    case Cash = 'cash';
    case Free = 'free';
    case Manual = 'manual';
    case Restored = 'restored';
}
