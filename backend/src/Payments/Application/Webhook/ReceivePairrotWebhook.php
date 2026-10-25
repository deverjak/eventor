<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Webhook;

/**
 * `POST /api/webhooks/pairrot` after the signature was verified: the raw body is stored in the inbox (ON CONFLICT DO
 * NOTHING) and its processing is queued, in one transaction. `source` is `webhook` or `reconcile`.
 */
final readonly class ReceivePairrotWebhook
{
    public function __construct(
        public string $body,
        public string $source = 'webhook',
    ) {}
}
