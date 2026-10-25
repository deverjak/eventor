<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Request;

/**
 * Whether Pairrot has confirmed the creation of the request (chapter 05, "Platební požadavek"): PENDING = requested,
 * CREATED = issued, FAILED = the retries were exhausted.
 */
enum CreationState: string
{
    case Pending = 'PENDING';
    case Created = 'CREATED';
    case Failed = 'FAILED';
}
