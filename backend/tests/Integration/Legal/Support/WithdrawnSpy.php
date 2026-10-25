<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Legal\Support;

use Eventor\Legal\Contract\Event\ConsentWithdrawn;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Test-only subscriber on the Legal transport: lets tests observe `ConsentWithdrawn` after the queue is drained,
 * although its real subscribers (Attention, Communication) may not exist yet.
 */
#[AsMessageHandler(fromTransport: 'legal_events')]
final class WithdrawnSpy
{
    /** @var list<ConsentWithdrawn> */
    public static array $received = [];

    public function __invoke(ConsentWithdrawn $event): void
    {
        self::$received[] = $event;
    }
}
