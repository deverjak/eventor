<?php

declare(strict_types=1);

namespace Eventor\Attention\Contract;

/**
 * Lifecycle of an attention item (chapter 05, "Výjimka"): open -> acknowledged -> resolved | dismissed.
 * Resolved = the cause is gone or was handled; dismissed = a human decided no action is needed.
 */
enum AttentionState: string
{
    case Open = 'open';
    case Acknowledged = 'acknowledged';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    public function isUnresolved(): bool
    {
        return self::Open === $this || self::Acknowledged === $this;
    }
}
