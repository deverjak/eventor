<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Correct;

use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * REG-09, data correction: the orderer fixes the contact of the order (the order-scoped answers of the form).
 */
final readonly class CorrectOrderData
{
    /**
     * @param array<string, mixed> $answers raw answers of the order-scoped fields
     */
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public array $answers,
    ) {}
}
