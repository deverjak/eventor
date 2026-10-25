<?php

declare(strict_types=1);

namespace Eventor\Tests\Fixtures\Messenger;

use Eventor\Identity\Contract\Event\MembershipChanged;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Fails its first two attempts, then succeeds: proves retry through the schedule.
 */
#[AsMessageHandler(fromTransport: 'probe_flaky')]
final readonly class FlakyProbeSubscriber
{
    public function __construct(private ProbeRecorder $recorder) {}

    public function __invoke(MembershipChanged $event): void
    {
        if ($this->recorder->attempt('flaky') <= 2) {
            throw new \RuntimeException('Flaky subscriber: simulated outage.');
        }

        $this->recorder->received('flaky', $event, null, null);
    }
}
