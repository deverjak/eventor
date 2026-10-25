<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\Organizer;

/**
 * Editable public and billing identity of an organizer (ORG-01, ORG-05): name, contact, billing/identification data,
 * the contact e-mail shown to participants and the default look of the public pages (logo, colour, cover as plain fields).
 */
final readonly class OrganizerProfile
{
    public function __construct(
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

    /**
     * @return list<string> names of the fields that differ from the other profile
     */
    public function changedFields(self $other): array
    {
        $changed = [];
        $theirs = get_object_vars($other);
        foreach (get_object_vars($this) as $field => $value) {
            if ($theirs[$field] !== $value) {
                $changed[] = $field;
            }
        }

        return $changed;
    }
}
