<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract;

/**
 * Publication state of an event (chapter 05, EVT-03).
 */
enum PublicationState: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Archived = 'archived';
}
