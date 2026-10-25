<?php

declare(strict_types=1);

namespace Eventor\Shared\Domain\Audit;

/**
 * A domain event that must leave an audit entry. DomainEventsMiddleware writes it in the command transaction
 * with the actor of the current AuditContext, so audit can neither be forgotten nor delayed (AUD-01).
 */
interface Auditable
{
    public function auditChange(): AuditChange;
}
