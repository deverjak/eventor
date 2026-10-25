<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract;

use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\CapacityBucketId;

/**
 * One activity of the offer. `minorsOnly` activities require the guardian fields and guardian confirmations (LEG-11).
 * `capacityBucketId` null = no activity-level limit. Waves are P1.
 */
final readonly class ActivitySnapshot
{
    /**
     * @param list<VariantSnapshot> $variants
     * @param list<WaveSnapshot> $waves
     */
    public function __construct(
        public ActivityId $id,
        public string $name,
        public ?\DateTimeImmutable $startsAt,
        public ?\DateTimeImmutable $endsAt,
        public RegistrationState $registrationState,
        public ?int $minAge,
        public ?int $maxAge,
        public bool $minorsOnly,
        public ?CapacityBucketId $capacityBucketId,
        public array $variants,
        public array $waves,
    ) {}

    public function acceptsRegistrations(): bool
    {
        return RegistrationState::Open === $this->registrationState;
    }
}
