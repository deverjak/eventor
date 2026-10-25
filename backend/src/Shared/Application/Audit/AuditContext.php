<?php

declare(strict_types=1);

namespace Eventor\Shared\Application\Audit;

use Eventor\Shared\Domain\Audit\AuditActor;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Actor and correlation id of the work in progress. AuditContextMiddleware fills it from the security token
 * (HTTP), from the ActorStamp / CorrelationStamp (worker) or with a system actor (console); reset between requests.
 */
final class AuditContext implements ResetInterface
{
    private ?AuditActor $actor = null;
    private ?string $correlationId = null;

    public function actor(): ?AuditActor
    {
        return $this->actor;
    }

    public function correlationId(): string
    {
        return $this->correlationId ??= Uuid::v7()->toRfc4122();
    }

    public function hasCorrelationId(): bool
    {
        return null !== $this->correlationId;
    }

    public function start(?AuditActor $actor, ?string $correlationId): void
    {
        $this->actor = $actor;
        $this->correlationId = $correlationId;
    }

    /**
     * Runs $work as another actor (e.g. a subscriber acting as "system") and restores the previous one.
     *
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function as(AuditActor $actor, callable $work): mixed
    {
        $previous = $this->actor;
        $this->actor = $actor;
        try {
            return $work();
        } finally {
            $this->actor = $previous;
        }
    }

    public function reset(): void
    {
        $this->actor = null;
        $this->correlationId = null;
    }
}
