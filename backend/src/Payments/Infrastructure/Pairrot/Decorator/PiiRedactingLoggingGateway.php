<?php

declare(strict_types=1);

namespace Eventor\Payments\Infrastructure\Pairrot\Decorator;

use Eventor\Payments\Application\Port\Exception\PaymentGatewayException;
use Eventor\Payments\Application\Port\PaymentGateway;
use Eventor\Shared\Application\Audit\AuditContext;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * Outermost decorator: logs the operation, the `external_id`, the outcome, the duration and the correlation id of
 * every call, including the ones the breaker refused. Never the IBAN, the message to the payee, an account number or
 * a response body (NFR personal data, chapter 08): the context is limited to an allow-list of keys and every value is
 * checked against it again.
 */
#[AsDecorator(decorates: PaymentGateway::class, priority: 10)]
#[WithMonologChannel('pairrot')]
final class PiiRedactingLoggingGateway implements PaymentGateway
{
    use DecoratesGateway;

    private const array SAFE_KEYS = ['external_id', 'request_id', 'transaction_id', 'connection_id', 'subtenant_id', 'limit'];

    public function __construct(
        #[AutowireDecorated]
        private readonly PaymentGateway $gateway,
        private readonly LoggerInterface $logger,
        private readonly AuditContext $audit,
    ) {}

    protected function inner(): PaymentGateway
    {
        return $this->gateway;
    }

    protected function call(string $operation, array $context, callable $call): mixed
    {
        $safe = ['operation' => $operation, 'correlation_id' => $this->audit->correlationId()];
        foreach ($context as $key => $value) {
            if (\in_array($key, self::SAFE_KEYS, true)) {
                $safe[$key] = $value;
            }
        }

        $started = hrtime(true);
        try {
            $result = $call($this->gateway);
        } catch (\Throwable $e) {
            $this->logger->warning('Pairrot call failed.', $safe + [
                'outcome' => 'failed',
                'error' => $e instanceof PaymentGatewayException ? $e->errorCode() : $e::class,
                'duration_ms' => $this->elapsed($started),
            ]);

            throw $e;
        }

        $this->logger->info('Pairrot call succeeded.', $safe + ['outcome' => 'ok', 'duration_ms' => $this->elapsed($started)]);

        return $result;
    }

    private function elapsed(int $started): int
    {
        return (int) ((hrtime(true) - $started) / 1_000_000);
    }
}
