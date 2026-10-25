<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query;

/**
 * How a number of free places is shown to the public (PUB-03): available / last places / sold out / unlimited.
 */
final class AvailabilityView
{
    /** At most this many places left is shown as "last places". */
    public const int LOW = 5;

    /**
     * @return array{status: string, available: int|null}
     */
    public static function describe(?int $available): array
    {
        $status = null === $available ? 'unlimited' : ($available <= 0 ? 'sold_out' : ($available <= self::LOW ? 'low' : 'available'));

        return ['status' => $status, 'available' => $available];
    }
}
