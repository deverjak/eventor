<?php

declare(strict_types=1);

namespace Eventor\Legal\Application\RequestReconfirmation;

/**
 * Internal command dispatched when a version flagged "requires reconfirmation" was published (LEG-08, S15): finds who
 * confirmed an older version and announces it. It never grants anything; silence is not consent.
 */
final readonly class RequestReconfirmation
{
    public function __construct(
        public string $documentId,
        public string $versionId,
    ) {}
}
