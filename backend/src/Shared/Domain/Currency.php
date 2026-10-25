<?php

declare(strict_types=1);

namespace Eventor\Shared\Domain;

/**
 * ISO 4217 currencies the platform accepts. P0 is CZK only, the type exists so that Money always carries one.
 */
enum Currency: string
{
    case CZK = 'CZK';
    case EUR = 'EUR';

    /** Number of minor units per major unit (haléř = 1/100 Kč). */
    public function minorUnitsPerMajor(): int
    {
        return 100;
    }
}
