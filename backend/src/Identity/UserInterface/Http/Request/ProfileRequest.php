<?php

declare(strict_types=1);

namespace Eventor\Identity\UserInterface\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body of `PUT /api/admin/org/{orgSlug}/profile`: the whole editable profile. The slug cannot be changed.
 */
final readonly class ProfileRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 200)]
        public string $name = '',
        #[Assert\Email]
        #[Assert\Length(max: 255)]
        public ?string $contactEmail = null,
        #[Assert\Length(max: 50)]
        public ?string $contactPhone = null,
        #[Assert\Length(max: 200)]
        public ?string $legalName = null,
        #[Assert\Length(max: 20)]
        public ?string $companyId = null,
        #[Assert\Length(max: 20)]
        public ?string $vatId = null,
        #[Assert\Length(max: 200)]
        public ?string $billingStreet = null,
        #[Assert\Length(max: 100)]
        public ?string $billingCity = null,
        #[Assert\Length(max: 20)]
        public ?string $billingPostalCode = null,
        #[Assert\Regex('/^[A-Za-z]{2}$/', message: 'Use a two-letter country code like CZ.')]
        public ?string $billingCountry = null,
        #[Assert\Url(requireTld: true)]
        #[Assert\Length(max: 500)]
        public ?string $logoUrl = null,
        #[Assert\Url(requireTld: true)]
        #[Assert\Length(max: 500)]
        public ?string $coverImageUrl = null,
        #[Assert\Regex('/^#[0-9a-fA-F]{6}$/', message: 'Use a colour like #1a73e8.')]
        public ?string $brandColor = null,
    ) {}
}
