<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\UpdateProfile;

use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Replaces the editable profile of the organizer (ORG-01, ORG-05): every field is sent, a missing optional one clears it.
 */
final readonly class UpdateOrganizerProfile
{
    public function __construct(
        public OrganizerId $organizerId,
        public string $name,
        public ?string $contactEmail = null,
        public ?string $contactPhone = null,
        public ?string $legalName = null,
        public ?string $companyId = null,
        public ?string $vatId = null,
        public ?string $billingStreet = null,
        public ?string $billingCity = null,
        public ?string $billingPostalCode = null,
        public ?string $billingCountry = null,
        public ?string $logoUrl = null,
        public ?string $coverImageUrl = null,
        public ?string $brandColor = null,
    ) {}
}
