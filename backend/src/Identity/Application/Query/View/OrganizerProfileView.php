<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\Query\View;

/**
 * Response of `GET /api/admin/org/{orgSlug}/profile`.
 */
final readonly class OrganizerProfileView
{
    public function __construct(
        public string $id,
        public string $slug,
        public string $name,
        public ?string $contactEmail,
        public ?string $contactPhone,
        public ?string $legalName,
        public ?string $companyId,
        public ?string $vatId,
        public ?string $billingStreet,
        public ?string $billingCity,
        public ?string $billingPostalCode,
        public ?string $billingCountry,
        public ?string $logoUrl,
        public ?string $coverImageUrl,
        public ?string $brandColor,
    ) {}
}
