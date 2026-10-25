<?php

declare(strict_types=1);

namespace Eventor\Privacy\Contract;

/**
 * Retention class of a table with personal data (chapter 03, "Manifest vlastnictví a retence").
 */
enum RetentionClass: string
{
    case Registration = 'registration';
    case Financial = 'financial';
    case Confirmation = 'confirmation';
    case Media = 'media';
}
