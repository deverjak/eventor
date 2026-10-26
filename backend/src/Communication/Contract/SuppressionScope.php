<?php

declare(strict_types=1);

namespace Eventor\Communication\Contract;

/**
 * `marketing`: no campaign with the marketing purpose to this address; `all`: nothing at all (a hard bounce).
 */
enum SuppressionScope: string
{
    case Marketing = 'marketing';
    case All = 'all';
}
