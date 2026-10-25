<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract;

/**
 * Where the event happens (map link only, never calls a map API).
 */
final readonly class VenueView
{
    public function __construct(
        public string $name,
        public ?string $address,
        public ?string $mapUrl,
    ) {}
}
