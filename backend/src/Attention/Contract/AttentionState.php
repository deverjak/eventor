<?php

declare(strict_types=1);

namespace Eventor\Attention\Contract;

/**
 * Lifecycle of an attention item (chapter 05, "Výjimka").
 */
enum AttentionState: string
{
    case Open = 'open';
    case Assigned = 'assigned';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';
}
