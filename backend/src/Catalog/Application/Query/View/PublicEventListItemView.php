<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query\View;

final readonly class PublicEventListItemView
{
    public function __construct(
        public string $slug,
        public string $name,
        public ?string $shortDescription,
        public ?string $coverImageUrl,
        public \DateTimeImmutable $startsAt,
        public \DateTimeImmutable $endsAt,
        public string $publicationState,
        public string $registrationState,
    ) {}
}
