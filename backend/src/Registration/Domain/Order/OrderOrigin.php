<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Order;

/**
 * Where an order came from: the public form, or typed in by the organizer (REG-13, P1).
 */
enum OrderOrigin: string
{
    case Public = 'public';
    case Organizer = 'organizer';
}
