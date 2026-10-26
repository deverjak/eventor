<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Application\RaiseEscalation;

/**
 * The operator passes a situation of the desk on to a manager (CHK-09): the situation is worked out again on the server.
 */
final readonly class RaiseEscalation
{
    public function __construct(
        public string $eventId,
        public string $listId,
        public string $participationId,
    ) {}
}
