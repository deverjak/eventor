<?php

declare(strict_types=1);

namespace Eventor\Tests\Fixtures\Doctrine;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Money;
use Eventor\Shared\Domain\Tenant\TenantScoped;

/**
 * Test-only entity (temporary table `priced_item`, created inside the test transaction): proves the Money
 * embeddable, the typed-id DBAL type and the TenantScoped filter against a real database.
 */
#[ORM\Entity]
#[ORM\Table(name: 'priced_item')]
class PricedItem implements TenantScoped
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'guid')]
        private string $id,
        #[ORM\Column(type: 'organizer_id')]
        private OrganizerId $organizerId,
        #[ORM\Embedded(class: Money::class, columnPrefix: 'price_')]
        private Money $price,
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function organizerId(): OrganizerId
    {
        return $this->organizerId;
    }

    public function price(): Money
    {
        return $this->price;
    }
}
