<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Order;

use Doctrine\ORM\Mapping as ORM;

/**
 * The contact of the person who placed the order (a parent for the children). Personal data: retention class
 * `REGISTRATION`, never copied onto the bus, into logs or into audit.
 */
#[ORM\Embeddable]
final readonly class Orderer
{
    public function __construct(
        #[ORM\Column(type: 'string', length: 100, nullable: true)]
        public ?string $firstName,
        #[ORM\Column(type: 'string', length: 100, nullable: true)]
        public ?string $lastName,
        #[ORM\Column(type: 'string', length: 255)]
        public string $email,
        #[ORM\Column(type: 'string', length: 50, nullable: true)]
        public ?string $phone,
        #[ORM\Column(type: 'string', length: 8)]
        public string $locale = 'cs',
    ) {}

    public function displayName(): string
    {
        $name = trim(($this->firstName ?? '').' '.($this->lastName ?? ''));

        return '' === $name ? $this->email : $name;
    }
}
