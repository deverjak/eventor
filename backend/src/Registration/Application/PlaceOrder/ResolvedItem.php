<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\PlaceOrder;

use Eventor\Catalog\Contract\ActivitySnapshot;
use Eventor\Catalog\Contract\VariantSnapshot;
use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * An item of the request matched with the offer: the activity and the variant as they were published, the reference
 * the legal decisions use, and the id the participation will get.
 */
final readonly class ResolvedItem
{
    public function __construct(
        public OrderItemInput $input,
        public string $itemRef,
        public ActivitySnapshot $activity,
        public VariantSnapshot $variant,
        public ParticipationId $participationId,
        public ?\DateTimeImmutable $birthDate = null,
        public bool $minor = false,
    ) {}

    public function withPerson(?\DateTimeImmutable $birthDate, bool $minor): self
    {
        return new self($this->input, $this->itemRef, $this->activity, $this->variant, $this->participationId, $birthDate, $minor);
    }
}
