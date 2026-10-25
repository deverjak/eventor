<?php

declare(strict_types=1);

namespace Eventor\Tests\Fixtures\Messenger;

use Eventor\Identity\Contract\Event\MembershipChanged;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Always fails: after the six attempts of the retry schedule the message is dead-lettered.
 */
#[AsMessageHandler(fromTransport: 'probe_failing')]
final readonly class FailingProbeSubscriber
{
    public function __construct(private ProbeRecorder $recorder) {}

    public function __invoke(MembershipChanged $event): void
    {
        $this->recorder->attempt('failing');

        throw new \RuntimeException('Failing subscriber: this message can never be processed.');
    }
}
