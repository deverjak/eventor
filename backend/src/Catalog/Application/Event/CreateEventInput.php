<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Event;

use Eventor\Shared\Domain\Money;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body of `POST /events` (EVT-01, EVT-02). `mode` "simple" creates the implicit activity with one variant (priced
 * with `price`, limited by `capacityLimit`); "multi" leaves the structure to the organizer.
 */
final readonly class CreateEventInput
{
    public function __construct(
        #[Assert\Valid]
        public EventDetailsInput $details,
        #[Assert\Regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'The address may contain lower-case letters, digits and single hyphens.')]
        #[Assert\Length(min: 2, max: 100)]
        public ?string $slug = null,
        #[Assert\Choice(choices: ['simple', 'multi'])]
        public string $mode = 'simple',
        public ?Money $price = null,
        #[Assert\PositiveOrZero]
        public ?int $capacityLimit = null,
    ) {}
}
