<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Webhook;

/**
 * Queue message on the `pairrot_inbox` transport (routing in the module's services.php): apply the inbox row.
 */
final readonly class ProcessInboxMessage
{
    public function __construct(public string $inboxMessageId) {}
}
