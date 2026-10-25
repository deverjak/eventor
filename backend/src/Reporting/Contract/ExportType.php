<?php

declare(strict_types=1);

namespace Eventor\Reporting\Contract;

/**
 * What a CSV export contains (ADM-07).
 */
enum ExportType: string
{
    case Registrations = 'registrations';
    case Finance = 'finance';
    case CheckIn = 'check_in';
    case Messages = 'messages';
}
