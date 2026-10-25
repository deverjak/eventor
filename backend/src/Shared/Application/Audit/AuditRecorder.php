<?php

declare(strict_types=1);

namespace Eventor\Shared\Application\Audit;

/**
 * Port to the Audit module's append-only `audit.audit_entry`. The implementation writes in the caller's
 * transaction (no outbox): audit must never be missing or late.
 */
interface AuditRecorder
{
    public function record(AuditRecord $record): void;
}
