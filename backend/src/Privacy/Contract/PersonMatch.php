<?php

declare(strict_types=1);

namespace Eventor\Privacy\Contract;

use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\PersonId;

/**
 * A person found, with the places that hold their data.
 */
final readonly class PersonMatch
{
    /**
     * @param list<OrderId> $orderIds
     * @param list<EventId> $eventIds
     */
    public function __construct(
        public PersonId $personId,
        public string $displayName,
        public array $orderIds,
        public array $eventIds,
        public bool $anonymized,
    ) {}
}
