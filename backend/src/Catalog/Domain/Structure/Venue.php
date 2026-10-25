<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Structure;

/**
 * A place where the event happens (EVT-01, 1..N). Only a map link, never a call to a map API.
 */
final readonly class Venue
{
    public function __construct(
        public string $name,
        public ?string $address = null,
        public ?string $mapUrl = null,
    ) {}

    /**
     * @return array{name: string, address: string|null, mapUrl: string|null}
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'address' => $this->address, 'mapUrl' => $this->mapUrl];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            \is_string($data['name'] ?? null) ? $data['name'] : '',
            \is_string($data['address'] ?? null) ? $data['address'] : null,
            \is_string($data['mapUrl'] ?? null) ? $data['mapUrl'] : null,
        );
    }
}
