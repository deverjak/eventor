<?php

declare(strict_types=1);

namespace Eventor\Shared\Application\Messaging;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Write side of the application: a command runs through the `command.bus` middleware chain
 * (validation, tenant, idempotency, transaction, audit, domain events) in ONE transaction.
 * Reads never go through a bus: controllers and other modules call query services.
 */
interface CommandBus
{
    /**
     * Dispatches the command and returns its handler's result. Exceptions thrown by the handler are re-thrown
     * as they are (not wrapped in HandlerFailedException).
     *
     * @return mixed the handler result (JSON-serialisable when the command is idempotent)
     */
    public function dispatch(object $command, StampInterface ...$stamps): mixed;
}
