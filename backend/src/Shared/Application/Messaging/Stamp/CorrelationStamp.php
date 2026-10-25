<?php

declare(strict_types=1);

namespace Eventor\Shared\Application\Messaging\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * The correlation id travels with every message, so one registration can be followed from the HTTP request
 * through payment to the e-mail (chapter 06, "Korelace").
 */
final readonly class CorrelationStamp implements StampInterface
{
    public function __construct(public string $correlationId) {}
}
