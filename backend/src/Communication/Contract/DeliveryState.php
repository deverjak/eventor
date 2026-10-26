<?php

declare(strict_types=1);

namespace Eventor\Communication\Contract;

/**
 * Chapter 05, "Doručení zprávy". `delivered` is reserved for provider callbacks (P1).
 */
enum DeliveryState: string
{
    case Scheduled = 'scheduled';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Skipped = 'skipped';
    case Failed = 'failed';
}
