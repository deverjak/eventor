<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query\View;

final readonly class ActivityDetailView
{
    /**
     * @param list<VariantDetailView> $variants
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description,
        public ?string $location,
        public ?\DateTimeImmutable $startsAt,
        public ?\DateTimeImmutable $endsAt,
        public bool $implicit,
        public string $registrationState,
        public ?\DateTimeImmutable $registrationOpensAt,
        public ?\DateTimeImmutable $registrationClosesAt,
        public ?int $minAge,
        public ?int $maxAge,
        public bool $minorsOnly,
        public ?int $capacityLimit,
        public string $capacityBucketId,
        public int $position,
        public int $version,
        public array $variants,
    ) {}
}
