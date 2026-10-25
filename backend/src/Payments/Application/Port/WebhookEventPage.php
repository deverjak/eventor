<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Port;

/**
 * One page of the Pairrot event log. `events` are the webhook envelopes exactly as Pairrot would have delivered them
 * (`id`, `type`, `data`, ...), so they enter the inbox under the same message id (chapter 07, "Dorovnávací job").
 */
final readonly class WebhookEventPage
{
    /**
     * @param list<array<string, mixed>> $events
     */
    public function __construct(
        public array $events,
        public ?string $nextCursor,
    ) {}
}
