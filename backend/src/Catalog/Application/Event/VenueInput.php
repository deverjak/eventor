<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Event;

use Eventor\Catalog\Domain\Structure\Venue;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A place of the event (EVT-01): name, address and a link to a map (never resolved by the server).
 */
final readonly class VenueInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 200)]
        public string $name,
        #[Assert\Length(max: 300)]
        public ?string $address = null,
        #[Assert\Url(requireTld: false)]
        #[Assert\Length(max: 500)]
        public ?string $mapUrl = null,
    ) {}

    public function toVenue(): Venue
    {
        return new Venue(trim($this->name), $this->address, $this->mapUrl);
    }
}
