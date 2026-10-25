<?php

declare(strict_types=1);

namespace Eventor\Shared\Application\Audit;

use Eventor\Shared\Domain\Audit\AuditActor;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * A change enriched with the context that only the application knows: actor, correlation id, time.
 */
final readonly class AuditRecord
{
    public function __construct(
        public AuditChange $change,
        public AuditActor $actor,
        public string $correlationId,
        public \DateTimeImmutable $occurredAt,
        public ?OrganizerId $organizerId,
    ) {}
}
