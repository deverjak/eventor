<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Delivery;

/**
 * What an attempt did; `Retry` asks the queue to come back later.
 */
enum AttemptOutcome: string
{
    case Sent = 'sent';
    case Skipped = 'skipped';
    case Failed = 'failed';
    case Retry = 'retry';
    case Ignored = 'ignored';
}
