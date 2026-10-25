<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\ChangeOrderAmountDue;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * System command of the `registration.order_amount_changed` subscriber (S18, REG-14): the amount due of the order is
 * now `newAmountDueMinor`; the financial state is recomputed against it.
 */
final readonly class ChangeOrderAmountDue
{
    public function __construct(
        #[Assert\NotBlank]
        public string $orderId,
        #[Assert\PositiveOrZero]
        public int $newAmountDueMinor,
        #[Assert\NotBlank]
        public string $reason,
    ) {}
}
