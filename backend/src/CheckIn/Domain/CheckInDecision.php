<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Domain;

/**
 * What the desk may do with a participant (CHK-09): `Allow` check in; `Warn` check in after the operator acknowledged
 * the warning (or settled it, for example with cash); `Escalate` pass it on to a manager; `Block` refuse.
 */
enum CheckInDecision: string
{
    case Allow = 'allow';
    case Warn = 'warn';
    case Escalate = 'escalate';
    case Block = 'block';
}
