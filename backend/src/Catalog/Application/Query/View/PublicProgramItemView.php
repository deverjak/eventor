<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query\View;

final readonly class PublicProgramItemView
{
    public function __construct(
        public string $id,
        public ?string $activityId,
        public string $title,
        public ?string $description,
        public ?string $location,
        public \DateTimeImmutable $startsAt,
        public ?\DateTimeImmutable $endsAt,
    ) {}
}
