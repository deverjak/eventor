<?php

declare(strict_types=1);

namespace Eventor\Payments\Infrastructure\Pairrot\Decorator;

use Eventor\Payments\Application\Port\Exception\PaymentGatewayUnavailable;
use Eventor\Payments\Application\Port\PaymentGateway;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * Innermost decorator: repeats a call that failed with `PaymentGatewayUnavailable` twice, after about 200 ms and 800 ms
 * (+-25 % jitter). Safe for every operation of the port because they are all idempotent (the creation by `external_id`).
 * A longer outage is the business of the outbox backoff, not of blocking sleeps (chapter 07, rule 3).
 *
 * Symfony applies the decorator with the HIGHEST priority first, so retry (30) is closest to the HTTP adapter,
 * the breaker (20) wraps it and the logging (10) is outermost.
 */
#[AsDecorator(decorates: PaymentGateway::class, priority: 30)]
final class RetryingGateway implements PaymentGateway
{
    use DecoratesGateway;

    /** @var list<int> milliseconds before the 2nd and the 3rd attempt */
    public const array DELAYS_MS = [200, 800];

    public function __construct(
        #[AutowireDecorated]
        private readonly PaymentGateway $gateway,
        private readonly ClockInterface $clock,
    ) {}

    protected function inner(): PaymentGateway
    {
        return $this->gateway;
    }

    protected function call(string $operation, array $context, callable $call): mixed
    {
        $attempt = 0;
        while (true) {
            try {
                return $call($this->gateway);
            } catch (PaymentGatewayUnavailable $e) {
                if ($attempt >= \count(self::DELAYS_MS)) {
                    throw $e;
                }
                $delay = self::DELAYS_MS[$attempt];
                $jitter = random_int(-intdiv($delay, 4), intdiv($delay, 4));
                $this->clock->sleep(($delay + $jitter) / 1000);
                ++$attempt;
            }
        }
    }
}
