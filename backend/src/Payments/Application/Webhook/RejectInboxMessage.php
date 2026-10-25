<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Webhook;

/**
 * A verified message that cannot be applied (unknown tenant or payment request): it stays in the inbox as `rejected`
 * and, when a tenant is known, an attention item says why (`integration_inconsistency`).
 */
final readonly class RejectInboxMessage
{
    public function __construct(
        public string $inboxMessageId,
        public string $reason,
    ) {}
}
