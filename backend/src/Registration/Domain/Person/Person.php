<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Person;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\PersonId;
use Eventor\Shared\Domain\Tenant\TenantScoped;

/**
 * A human of an order: a participant, or the guardian of a minor, or the orderer when an order-level confirmation
 * needs a subject. The semantic form fields (FRM-04) are typed columns because age, category and addressing are
 * computed from them; everything else is in the answer set. Personal data (retention class `REGISTRATION`):
 * anonymisation overwrites the columns and keeps the row (orders and the ledger refer to it).
 */
#[ORM\Entity]
#[ORM\Table(name: 'person', schema: 'registration')]
class Person implements TenantScoped
{
    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $anonymizedAt = null;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'person_id')]
        private PersonId $id,
        #[ORM\Column(type: 'organizer_id')]
        private OrganizerId $organizerId,
        #[ORM\Column(type: 'order_id')]
        private OrderId $orderId,
        #[ORM\Column(type: 'string', length: 100, nullable: true)]
        private ?string $firstName,
        #[ORM\Column(type: 'string', length: 100, nullable: true)]
        private ?string $lastName,
        #[ORM\Column(type: 'date_immutable', nullable: true)]
        private ?\DateTimeImmutable $birthDate,
        #[ORM\Column(type: 'string', length: 255, nullable: true)]
        private ?string $email,
        #[ORM\Column(type: 'string', length: 50, nullable: true)]
        private ?string $phone,
        #[ORM\Column(type: 'person_id', nullable: true)]
        private ?PersonId $guardianPersonId = null,
    ) {}

    public function id(): PersonId
    {
        return $this->id;
    }

    public function organizerId(): OrganizerId
    {
        return $this->organizerId;
    }

    public function orderId(): OrderId
    {
        return $this->orderId;
    }

    public function firstName(): ?string
    {
        return $this->firstName;
    }

    public function lastName(): ?string
    {
        return $this->lastName;
    }

    public function birthDate(): ?\DateTimeImmutable
    {
        return $this->birthDate;
    }

    public function email(): ?string
    {
        return $this->email;
    }

    public function phone(): ?string
    {
        return $this->phone;
    }

    public function guardianPersonId(): ?PersonId
    {
        return $this->guardianPersonId;
    }

    public function displayName(): string
    {
        if (null !== $this->anonymizedAt) {
            return 'Anonymizovaná osoba';
        }
        $name = trim(($this->firstName ?? '').' '.($this->lastName ?? ''));

        return '' === $name ? '-' : $name;
    }

    /**
     * Data correction (REG-09): the semantic columns follow the new answers. A field that is not asked any more stays empty.
     */
    public function correct(?string $firstName, ?string $lastName, ?\DateTimeImmutable $birthDate, ?string $email, ?string $phone): void
    {
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->birthDate = $birthDate;
        $this->email = $email;
        $this->phone = $phone;
    }

    public function assignGuardian(PersonId $guardian): void
    {
        $this->guardianPersonId = $guardian;
    }

    public function anonymize(string $label, \DateTimeImmutable $now): void
    {
        $this->firstName = $label;
        $this->lastName = null;
        $this->email = null;
        $this->phone = null;
        $this->birthDate = null !== $this->birthDate ? $this->birthDate->setDate((int) $this->birthDate->format('Y'), 1, 1) : null;
        $this->anonymizedAt = $now;
    }

    public function isAnonymized(): bool
    {
        return null !== $this->anonymizedAt;
    }
}
