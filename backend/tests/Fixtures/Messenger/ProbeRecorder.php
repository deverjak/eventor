<?php

declare(strict_types=1);

namespace Eventor\Tests\Fixtures\Messenger;

use Symfony\Contracts\Service\ResetInterface;

/**
 * Collects what the probe subscribers saw. Public service of the test environment.
 */
final class ProbeRecorder implements ResetInterface
{
    /** When true, FailAfterPublishHandler throws after PublishMembershipChanged has queued the integration event. */
    public bool $failAfterPublish = false;

    /** @var array<string, list<array{event: object, tenant: string|null, actor: string|null}>> */
    private array $received = [];

    /** @var array<string, int> */
    private array $attempts = [];

    public function received(string $subscriber, object $event, ?string $tenant, ?string $actor): void
    {
        $this->received[$subscriber][] = ['event' => $event, 'tenant' => $tenant, 'actor' => $actor];
    }

    public function attempt(string $subscriber): int
    {
        return $this->attempts[$subscriber] = ($this->attempts[$subscriber] ?? 0) + 1;
    }

    public function attempts(string $subscriber): int
    {
        return $this->attempts[$subscriber] ?? 0;
    }

    /**
     * @return list<array{event: object, tenant: string|null, actor: string|null}>
     */
    public function receivedBy(string $subscriber): array
    {
        return $this->received[$subscriber] ?? [];
    }

    public function reset(): void
    {
        $this->received = [];
        $this->attempts = [];
        $this->failAfterPublish = false;
    }
}
