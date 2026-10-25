<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Event;

/**
 * Domain event: a Pairrot message was applied or rejected. Technical (no integration event, no audit): the effects of
 * the message record their own events.
 */
final readonly class InboxMessageSettled
{
    public function __construct(
        public string $inboxMessageId,
        public string $type,
        public string $status,
        public ?string $error,
    ) {}
}
