<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query\View;

final readonly class ProgramItemAdminView
{
    public function __construct(
        public string $id,
        public ?string $activityId,
        public string $title,
        public ?string $description,
        public ?string $location,
        public ?string $responsible,
        public \DateTimeImmutable $startsAt,
        public ?\DateTimeImmutable $endsAt,
        public string $visibility,
    ) {}
}
