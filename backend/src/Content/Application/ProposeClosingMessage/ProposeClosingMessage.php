<?php

declare(strict_types=1);

namespace Eventor\Content\Application\ProposeClosingMessage;

final readonly class ProposeClosingMessage
{
    public function __construct(public string $eventId) {}
}
