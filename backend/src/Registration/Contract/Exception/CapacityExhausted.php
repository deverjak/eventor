<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract\Exception;

use Eventor\Shared\Domain\Capacity\CapacityScope;
use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Id\CapacityBucketId;

/**
 * A bucket had no room for the order (S01, S02): the whole transaction is rolled back. With `waitlistAvailable` the client offers the waitlist (REG-12, P1).
 */
#[AsProblem(code: 'capacity_exhausted', status: 409)]
final class CapacityExhausted extends DomainException
{
    public function __construct(
        public readonly CapacityBucketId $bucketId,
        public readonly CapacityScope $scope,
        public readonly bool $waitlistAvailable = false,
    ) {
        parent::__construct(\sprintf('The %s capacity is exhausted.', $scope->value));
    }

    /**
     * @return array<string, scalar|null>
     */
    public function context(): array
    {
        return ['bucketId' => $this->bucketId->toString(), 'scope' => $this->scope->value, 'waitlistAvailable' => $this->waitlistAvailable];
    }

    /**
     * @return list<array{action: string, href?: string}>
     */
    public function nextSteps(): array
    {
        return $this->waitlistAvailable ? [['action' => 'join_waitlist']] : [];
    }
}
