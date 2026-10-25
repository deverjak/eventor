<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract;

use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\PersonId;

/**
 * A person found for Privacy. Display name only; the full record stays in Registration.
 */
final readonly class PersonRecordRef
{
    /**
     * @param list<OrderId> $orderIds
     */
    public function __construct(
        public PersonId $personId,
        public string $displayName,
        public array $orderIds,
        public bool $anonymized,
    ) {}
}
