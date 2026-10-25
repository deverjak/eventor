<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract;

/**
 * Registration window of an event or an activity (chapter 05, EVT-04): not_open -> open <-> paused -> closed.
 */
enum RegistrationState: string
{
    case NotOpen = 'not_open';
    case Open = 'open';
    case Paused = 'paused';
    case Closed = 'closed';
}
