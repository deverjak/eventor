<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query\View;

use Eventor\Catalog\Contract\VenueView;

/**
 * One event with its activities and variants (draft included), for the admin. `version` goes into `If-Match`.
 */
final readonly class EventDetailView
{
    /**
     * @param list<VenueView>          $venues
     * @param list<ActivityDetailView> $activities
     */
    public function __construct(
        public string $id,
        public string $slug,
        public string $name,
        public ?string $shortDescription,
        public ?string $description,
        public ?string $coverImageUrl,
        public ContactView $contact,
        public string $timezone,
        public \DateTimeImmutable $startsAt,
        public \DateTimeImmutable $endsAt,
        public ?string $ageReferenceDate,
        public string $visibility,
        public string $publicationState,
        public string $registrationState,
        public ?\DateTimeImmutable $registrationOpensAt,
        public ?\DateTimeImmutable $registrationClosesAt,
        public ?int $capacityLimit,
        public string $capacityBucketId,
        public PaymentRulesView $paymentRules,
        public array $venues,
        public int $priceListVersion,
        public ?string $cancellationReason,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public int $version,
        public array $activities,
    ) {}
}
