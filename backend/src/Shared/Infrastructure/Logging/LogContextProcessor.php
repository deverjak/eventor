<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Logging;

use Eventor\Shared\Application\Audit\AuditContext;
use Eventor\Shared\Application\Tenant\TenantContext;
use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;

/**
 * Adds the fields every Eventor log line carries (chapter 08): correlation_id, organizer_id, actor_type.
 */
#[AsMonologProcessor]
final readonly class LogContextProcessor
{
    public function __construct(
        private AuditContext $audit,
        private TenantContext $tenant,
    ) {}

    public function __invoke(LogRecord $record): LogRecord
    {
        $extra = $record->extra;
        if ($this->audit->hasCorrelationId()) {
            $extra['correlation_id'] = $this->audit->correlationId();
        }
        if (null !== $this->tenant->current()) {
            $extra['organizer_id'] = $this->tenant->current()->toString();
        }
        if (null !== $this->audit->actor()) {
            $extra['actor_type'] = $this->audit->actor()->type->value;
        }

        return $record->with(extra: $extra);
    }
}
