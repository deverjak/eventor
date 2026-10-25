<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query\View;

final readonly class PublicActivityView
{
    /**
     * @param list<PublicVariantView> $variants
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description,
        public ?string $location,
        public ?\DateTimeImmutable $startsAt,
        public ?\DateTimeImmutable $endsAt,
        public string $registrationState,
        public ?\DateTimeImmutable $registrationOpensAt,
        public ?\DateTimeImmutable $registrationClosesAt,
        public ?int $minAge,
        public ?int $maxAge,
        public bool $minorsOnly,
        public ?AvailabilityStatusView $availability,
        public array $variants,
    ) {}
}
