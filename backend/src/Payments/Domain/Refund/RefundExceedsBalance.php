<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Refund;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Money;

/**
 * A refund may return only money the order actually received and has not returned yet (received − refunded).
 */
#[AsProblem(code: 'refund_exceeds_balance', status: 422)]
final class RefundExceedsBalance extends DomainException
{
    private function __construct(string $message, private readonly int $requestedMinor, private readonly int $refundableMinor)
    {
        parent::__construct($message);
    }

    public static function of(Money $requested, Money $refundable): self
    {
        return new self(
            \sprintf('A refund of %d exceeds the refundable balance of %d (minor units).', $requested->minor, $refundable->minor),
            $requested->minor,
            $refundable->minor,
        );
    }

    public function context(): array
    {
        return ['requestedMinor' => $this->requestedMinor, 'refundableMinor' => $this->refundableMinor];
    }
}
