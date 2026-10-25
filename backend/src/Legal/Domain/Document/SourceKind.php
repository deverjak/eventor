<?php

declare(strict_types=1);

namespace Eventor\Legal\Domain\Document;

/**
 * Where the bytes of a version come from (LEG-01).
 */
enum SourceKind: string
{
    case Text = 'text';
    case Pdf = 'pdf';
    case ExternalSnapshot = 'external_snapshot';
}
