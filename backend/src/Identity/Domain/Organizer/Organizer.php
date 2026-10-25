<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\Organizer;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Identity\Domain\Event\OrganizerProfileUpdated;
use Eventor\Identity\Domain\Event\OrganizerRegistered;
use Eventor\Identity\Domain\Event\PairrotConnectedToOrganizer;
use Eventor\Shared\Domain\Event\RecordsEvents;
use Eventor\Shared\Domain\Event\RecordsEventsInterface;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * The tenant: an organization that runs events. Global (not tenant-scoped itself): its id is the tenant key of
 * every other tenant-scoped table.
 */
#[ORM\Entity]
#[ORM\Table(name: 'organizer', schema: 'identity')]
#[ORM\UniqueConstraint(name: 'organizer_slug_key', columns: ['slug'])]
class Organizer implements RecordsEventsInterface
{
    use RecordsEvents;

    #[ORM\Column(type: 'string', length: 64)]
    private string $slug;

    #[ORM\Column(type: 'string', length: 200)]
    private string $name;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $contactEmail = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $contactPhone = null;

    #[ORM\Column(type: 'string', length: 200, nullable: true)]
    private ?string $legalName = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $companyId = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $vatId = null;

    #[ORM\Column(type: 'string', length: 200, nullable: true)]
    private ?string $billingStreet = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $billingCity = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $billingPostalCode = null;

    #[ORM\Column(type: 'string', length: 2, nullable: true)]
    private ?string $billingCountry = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $logoUrl = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $coverImageUrl = null;

    #[ORM\Column(type: 'string', length: 7, nullable: true)]
    private ?string $brandColor = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $pairrotSubtenantId = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $pairrotBankConnectionId = null;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $maskedIban = null;

    #[ORM\Column(type: 'boolean')]
    private bool $transferEnabled = false;

    #[ORM\Column(type: 'boolean')]
    private bool $autoMatching = true;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'organizer_id')]
        private OrganizerId $id,
        string $slug,
        string $name,
        \DateTimeImmutable $createdAt,
    ) {
        $this->slug = $slug;
        $this->name = $name;
        $this->createdAt = $createdAt;
    }

    public static function register(OrganizerId $id, string $slug, string $name, \DateTimeImmutable $now, ?string $contactEmail = null): self
    {
        $organizer = new self($id, $slug, $name, $now);
        $organizer->contactEmail = $contactEmail;
        $organizer->record(new OrganizerRegistered($id, $slug, $name));

        return $organizer;
    }

    public function id(): OrganizerId
    {
        return $this->id;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function contactEmail(): ?string
    {
        return $this->contactEmail;
    }

    public function profile(): OrganizerProfile
    {
        return new OrganizerProfile(
            $this->name,
            $this->contactEmail,
            $this->contactPhone,
            $this->legalName,
            $this->companyId,
            $this->vatId,
            $this->billingStreet,
            $this->billingCity,
            $this->billingPostalCode,
            $this->billingCountry,
            $this->logoUrl,
            $this->coverImageUrl,
            $this->brandColor,
        );
    }

    public function updateProfile(OrganizerProfile $profile): void
    {
        $profile = new OrganizerProfile(
            $profile->name,
            $profile->contactEmail,
            $profile->contactPhone,
            $profile->legalName,
            $profile->companyId,
            $profile->vatId,
            $profile->billingStreet,
            $profile->billingCity,
            $profile->billingPostalCode,
            null === $profile->billingCountry ? null : strtoupper($profile->billingCountry),
            $profile->logoUrl,
            $profile->coverImageUrl,
            $profile->brandColor,
        );
        $changed = $this->profile()->changedFields($profile);

        $this->name = $profile->name;
        $this->contactEmail = $profile->contactEmail;
        $this->contactPhone = $profile->contactPhone;
        $this->legalName = $profile->legalName;
        $this->companyId = $profile->companyId;
        $this->vatId = $profile->vatId;
        $this->billingStreet = $profile->billingStreet;
        $this->billingCity = $profile->billingCity;
        $this->billingPostalCode = $profile->billingPostalCode;
        $this->billingCountry = $profile->billingCountry;
        $this->logoUrl = $profile->logoUrl;
        $this->coverImageUrl = $profile->coverImageUrl;
        $this->brandColor = $profile->brandColor;

        $this->record(new OrganizerProfileUpdated($this->id, $changed));
    }

    /**
     * Stores the result of the Pairrot onboarding and enables bank-transfer payments (ORG-02).
     */
    public function connectPairrot(string $subtenantId, string $bankConnectionId, string $maskedIban, bool $autoMatching = true): void
    {
        $this->pairrotSubtenantId = $subtenantId;
        $this->pairrotBankConnectionId = $bankConnectionId;
        $this->maskedIban = $maskedIban;
        $this->autoMatching = $autoMatching;
        $this->transferEnabled = true;

        $this->record(new PairrotConnectedToOrganizer($this->id, $subtenantId, $bankConnectionId));
    }

    public function pairrotSubtenantId(): ?string
    {
        return $this->pairrotSubtenantId;
    }
}
