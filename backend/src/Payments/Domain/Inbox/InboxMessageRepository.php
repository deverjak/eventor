<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Inbox;

interface InboxMessageRepository
{
    /**
     * The message with a row lock (two workers never apply the same message); only valid inside a transaction.
     */
    public function lock(string $id): ?InboxMessage;
}
