<?php

declare(strict_types=1);

namespace Eventor\Payments\Contract;

/**
 * State of the notification reception of a bank connection in Pairrot (chapter 07, ORG-02, step 4).
 */
enum ReceptionState: string
{
    case NotConfigured = 'not_configured';
    case Waiting = 'waiting';
    case Active = 'active';
    case Paused = 'paused';
}
