<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract;

use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\FormVersionId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Money;
use Eventor\Shared\Domain\Payment\PaymentMethod;

/**
 * An order with its participations. `amountDue` is the current amount after cancellations and changes.
 */
final readonly class OrderView
{
    /**
     * @param list<ParticipationView> $participations
     */
    public function __construct(
        public OrderId $id,
        public OrganizerId $organizerId,
        public string $number,
        public EventId $eventId,
        public PaymentMethod $paymentMethod,
        public Money $amountDue,
        public \DateTimeImmutable $placedAt,
        public FormVersionId $formVersionId,
        public ?\DateTimeImmutable $holdExpiresAt,
        public array $participations,
    ) {}
}
