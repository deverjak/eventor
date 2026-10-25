<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Legal\Support;

use Eventor\Legal\Contract\Event\ReconfirmationRequested;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Test-only subscriber on the Legal transport: lets tests observe `ReconfirmationRequested` after the queue is
 * drained, although its real subscribers (Communication, Registration) may not exist yet.
 */
#[AsMessageHandler(fromTransport: 'legal_events')]
final class ReconfirmationSpy
{
    /** @var list<ReconfirmationRequested> */
    public static array $received = [];

    public function __invoke(ReconfirmationRequested $event): void
    {
        self::$received[] = $event;
    }
}
