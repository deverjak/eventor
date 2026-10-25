<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Legal\Support;

use Eventor\Legal\Contract\Event\ConfirmationRecorded;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Test-only subscriber on the Legal transport: lets tests observe `ConfirmationRecorded` after the queue is drained,
 * although its real subscriber (Registration) may not exist yet.
 */
#[AsMessageHandler(fromTransport: 'legal_events')]
final class RecordedSpy
{
    /** @var list<ConfirmationRecorded> */
    public static array $received = [];

    public function __invoke(ConfirmationRecorded $event): void
    {
        self::$received[] = $event;
    }
}
