<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\PaymentMethod;

use Eventor\Catalog\Contract\PublishedEventSnapshot;
use Eventor\Registration\Domain\Exception\PaymentMethodNotAllowed;
use Eventor\Shared\Domain\Payment\PaymentMethod;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Picks the strategy of an order: a zero total is always `free`; otherwise the requested method, or the only one the
 * organizer enabled when the request names none.
 */
final readonly class PaymentMethodStrategies
{
    /**
     * @param iterable<PaymentMethodStrategy> $strategies
     */
    public function __construct(
        #[AutowireIterator('registration.payment_method_strategy')]
        private iterable $strategies,
    ) {}

    /**
     * @throws PaymentMethodNotAllowed
     */
    public function choose(PublishedEventSnapshot $offer, ?PaymentMethod $requested, bool $zeroTotal): PaymentMethodStrategy
    {
        if ($zeroTotal) {
            return $this->strategyOf(PaymentMethod::Free);
        }

        $method = $requested;
        if (null === $method) {
            if (1 !== \count($offer->paymentMethods)) {
                throw PaymentMethodNotAllowed::required();
            }
            $method = $offer->paymentMethods[0];
        }

        $strategy = $this->strategyOf($method);
        if (!$strategy->accepts(\in_array($method, $offer->paymentMethods, true), false)) {
            throw PaymentMethodNotAllowed::notOffered($method->value);
        }

        return $strategy;
    }

    public function strategyOf(PaymentMethod $method): PaymentMethodStrategy
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->method() === $method) {
                return $strategy;
            }
        }

        throw new \LogicException(\sprintf('No payment strategy for "%s".', $method->value));
    }
}
