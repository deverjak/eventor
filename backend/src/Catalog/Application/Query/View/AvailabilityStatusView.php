<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query\View;

/**
 * How a number of free places is shown to the public (PUB-03): available / last places / sold out / unlimited.
 */
final readonly class AvailabilityStatusView
{
    /** At most this many places left is shown as "last places". */
    public const int LOW = 5;

    public function __construct(
        public string $status,
        public ?int $available,
    ) {}

    public static function of(?int $available): self
    {
        $status = null === $available ? 'unlimited' : ($available <= 0 ? 'sold_out' : ($available <= self::LOW ? 'low' : 'available'));

        return new self($status, $available);
    }
}
