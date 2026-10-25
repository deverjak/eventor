<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Finance;

use Eventor\Payments\Contract\OrderFinanceInitializer;
use Eventor\Payments\Domain\Finance\OrderFinance;
use Eventor\Payments\Domain\Finance\OrderFinanceRepository;
use Eventor\Shared\Domain\Finance\FinancialStateView;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Money;
use Eventor\Shared\Domain\Payment\PaymentMethod;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * `PlaceOrder` step 8 (chapter 04): the finance row exists in the transaction that creates the order. Idempotent per order.
 * Nothing is published: Registration's own `order_placed` carries the amount and the method.
 */
#[AsAlias(OrderFinanceInitializer::class)]
final readonly class OrderFinanceInitializerService implements OrderFinanceInitializer
{
    public function __construct(
        private OrderFinanceRepository $finances,
        private ClockInterface $clock,
    ) {}

    public function initialize(OrganizerId $organizer, OrderId $order, PaymentMethod $method, Money $amountDue): FinancialStateView
    {
        $existing = $this->finances->find($order);
        if (null !== $existing) {
            return $existing->view();
        }

        $finance = OrderFinance::initialize($organizer, $order, $method, $amountDue, \DateTimeImmutable::createFromInterface($this->clock->now()));
        $this->finances->add($finance);

        return $finance->view();
    }
}
