<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Webhook;

/**
 * `review_case.*` and `inbound_address.*` events: they change no money, they tell the organizer something needs a
 * look in Pairrot (an attention item) or that notification reception is paused (`payments.integration_degraded`).
 */
final readonly class ApplyPairrotNotice
{
    public function __construct(public string $inboxMessageId) {}
}
