<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query\View;

/**
 * One row of the admin event list.
 */
final readonly class EventListItemView
{
    public function __construct(
        public string $id,
        public string $slug,
        public string $name,
        public ?string $shortDescription,
        public \DateTimeImmutable $startsAt,
        public \DateTimeImmutable $endsAt,
        public string $visibility,
        public string $publicationState,
        public string $registrationState,
        public int $version,
    ) {}
}
