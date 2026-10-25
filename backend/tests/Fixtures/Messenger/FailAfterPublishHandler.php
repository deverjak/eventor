<?php

declare(strict_types=1);

namespace Eventor\Tests\Fixtures\Messenger;

use Eventor\Identity\Domain\Event\MembershipGranted;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Synchronous domain-event handler that runs AFTER PublishMembershipChanged (lower priority) and throws on demand.
 * It proves that the outbox row written by the publisher is rolled back together with the rest of the command.
 */
#[AsMessageHandler(bus: 'event.bus', priority: -100)]
final readonly class FailAfterPublishHandler
{
    public function __construct(private ProbeRecorder $recorder) {}

    public function __invoke(MembershipGranted $event): void
    {
        if ($this->recorder->failAfterPublish) {
            throw new \RuntimeException('Probe: failing after publishing the integration event.');
        }
    }
}
