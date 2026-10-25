<?php

declare(strict_types=1);

namespace Eventor\Payments\Infrastructure\Pairrot\Decorator;

use Eventor\Payments\Application\Port\Exception\PaymentGatewayUnavailable;
use Eventor\Payments\Application\Port\GatewayAlerts;
use Eventor\Payments\Application\Port\PaymentGateway;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * After 5 consecutive retryable failures the breaker opens for 60 s and calls fail at once with
 * `PaymentGatewayUnavailable`, without a network attempt, so an outage does not pile up timeouts in the outbox
 * (S08). The state lives in a cache pool shared by the web and worker containers (Postgres in production).
 * After the pause one trial call is let through (half-open): success closes the breaker, failure reopens it.
 * Only `PaymentGatewayUnavailable` counts; a rejection or a conflict says Pairrot works.
 */
#[AsDecorator(decorates: PaymentGateway::class, priority: 20)]
final class CircuitBreakerGateway implements PaymentGateway
{
    use DecoratesGateway;

    public const int THRESHOLD = 5;
    public const int OPEN_SECONDS = 60;
    private const string KEY = 'payments.pairrot.circuit';

    public function __construct(
        #[AutowireDecorated]
        private readonly PaymentGateway $gateway,
        #[Autowire(service: 'payments.circuit_breaker')]
        private readonly CacheItemPoolInterface $cache,
        private readonly ClockInterface $clock,
        private readonly GatewayAlerts $alerts,
    ) {}

    protected function inner(): PaymentGateway
    {
        return $this->gateway;
    }

    protected function call(string $operation, array $context, callable $call): mixed
    {
        $now = $this->clock->now();
        $state = $this->state();
        if ($state['openedUntil'] > $now->getTimestamp()) {
            throw new PaymentGatewayUnavailable('Pairrot is paused by the circuit breaker.', 'pairrot_circuit_open');
        }

        try {
            $result = $call($this->gateway);
        } catch (PaymentGatewayUnavailable $e) {
            $this->failed($state, \DateTimeImmutable::createFromInterface($now));
            throw $e;
        } catch (\Throwable $e) {
            $this->succeeded($state);
            throw $e;
        }

        $this->succeeded($state);

        return $result;
    }

    /**
     * @param array{failures: int, openedUntil: int} $state
     */
    private function failed(array $state, \DateTimeImmutable $now): void
    {
        $failures = $state['failures'] + 1;
        $openedUntil = 0;
        if ($failures >= self::THRESHOLD) {
            $openedUntil = $now->getTimestamp() + self::OPEN_SECONDS;
            $this->alerts->circuitOpened($now, $now->modify('+'.self::OPEN_SECONDS.' seconds'));
        }
        $this->store(['failures' => $failures, 'openedUntil' => $openedUntil]);
    }

    /**
     * @param array{failures: int, openedUntil: int} $state
     */
    private function succeeded(array $state): void
    {
        if ($state['failures'] > 0 || $state['openedUntil'] > 0) {
            $this->cache->deleteItem(self::KEY);
        }
    }

    /**
     * @return array{failures: int, openedUntil: int}
     */
    private function state(): array
    {
        $value = $this->cache->getItem(self::KEY)->get();
        if (\is_array($value) && \is_int($value['failures'] ?? null) && \is_int($value['openedUntil'] ?? null)) {
            return ['failures' => $value['failures'], 'openedUntil' => $value['openedUntil']];
        }

        return ['failures' => 0, 'openedUntil' => 0];
    }

    /**
     * @param array{failures: int, openedUntil: int} $state
     */
    private function store(array $state): void
    {
        $item = $this->cache->getItem(self::KEY);
        $item->set($state)->expiresAfter(3600);
        $this->cache->save($item);
    }
}
