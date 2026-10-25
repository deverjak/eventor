<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract;

use Eventor\Shared\Domain\Id\AddOnId;
use Eventor\Shared\Domain\Money;

/**
 * An add-on (EVT-09, P1). `perPerson` = ordered for a person, otherwise for the whole order.
 */
final readonly class AddOnSnapshot
{
    public function __construct(
        public AddOnId $id,
        public string $name,
        public Money $price,
        public bool $perPerson,
        public ?int $maxQuantity,
    ) {}
}
