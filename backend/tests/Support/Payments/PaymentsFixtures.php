<?php

declare(strict_types=1);

namespace Eventor\Tests\Support\Payments;

use Doctrine\ORM\EntityManagerInterface;
use Eventor\Payments\Application\Webhook\ReceivePairrotWebhook;
use Eventor\Payments\Contract\OrderFinanceInitializer;
use Eventor\Payments\Contract\PairrotOnboarding;
use Eventor\Payments\Infrastructure\Pairrot\InMemoryPaymentGateway;
use Eventor\Registration\Contract\Event\OrderAmountChanged;
use Eventor\Registration\Contract\Event\OrderPlaced;
use Eventor\Registration\Contract\OrderAmountChangeReason;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Eventor\Shared\Application\Tenant\TenantContext;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Money;
use Eventor\Shared\Domain\Payment\PaymentMethod;
use Eventor\Shared\Infrastructure\Doctrine\TenantFilterSynchronizer;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Test arrangement for the Payments module: a connected organizer, an order with a finance row (what `PlaceOrder` does
 * in W3), the Registration events Payments subscribes to, signed Pairrot webhooks delivered to the inbox, and
 * commands dispatched inside a tenant. Use in an IntegrationTestCase / ScenarioTestCase.
 */
trait PaymentsFixtures
{
    public const string ACCOUNT = '19-2000145399/0800';

    /**
     * Creates the finance row of a new order in the transaction of the test.
     */
    protected function placeOrderFinance(OrganizerId $organizer, int $amountDue, PaymentMethod $method = PaymentMethod::BankTransfer, ?OrderId $order = null): OrderId
    {
        $order ??= OrderId::generate();
        $this->enterTenant($organizer);
        $this->service(OrderFinanceInitializer::class)->initialize($organizer, $order, $method, Money::of($amountDue));
        $this->entityManager()->flush();

        return $order;
    }

    /**
     * The Pairrot subtenant and bank connection of the organizer (what the onboarding wizard does), against the fake.
     */
    protected function connectOrganizer(OrganizerId $organizer, string $account = self::ACCOUNT): void
    {
        $onboarding = $this->service(PairrotOnboarding::class);
        $subtenant = $onboarding->activateSubtenant($organizer, 'Test club', 'club@example.test');
        $onboarding->connectBankAccount($organizer, $subtenant, $account, 'Test');
    }

    protected function pairrot(): InMemoryPaymentGateway
    {
        $gateway = static::getContainer()->get(InMemoryPaymentGateway::class);
        \assert($gateway instanceof InMemoryPaymentGateway);

        return $gateway;
    }

    /**
     * A bank-transfer order as Registration would place it: finance row now, `registration.order_placed` published.
     */
    protected function placeTransferOrder(OrganizerId $organizer, int $amountDue, string $number = 'EV-2026-000001', ?\DateTimeImmutable $holdExpiresAt = null): OrderId
    {
        $order = $this->placeOrderFinance($organizer, $amountDue);
        $this->publish($organizer, new OrderPlaced(
            $organizer,
            $order,
            $number,
            EventId::generate(),
            PaymentMethod::BankTransfer,
            Money::of($amountDue),
            [ParticipationId::generate()],
            $holdExpiresAt ?? new \DateTimeImmutable('2026-11-04T12:00:00+00:00'),
            new \DateTimeImmutable('2026-11-01T12:00:00+00:00'),
        ));

        return $order;
    }

    protected function changeAmountDue(OrganizerId $organizer, OrderId $order, int $previous, int $new, OrderAmountChangeReason $reason = OrderAmountChangeReason::VariantChanged): void
    {
        $this->publish($organizer, new OrderAmountChanged($organizer, $order, Money::of($previous), Money::of($new), $reason, new \DateTimeImmutable('2026-11-01T13:00:00+00:00')));
    }

    /**
     * Puts an integration event on the event bus the way a publisher does (the outbox routes it to the subscribers).
     */
    protected function publish(OrganizerId $organizer, object $event): void
    {
        $bus = static::getContainer()->get('test.event_bus');
        \assert($bus instanceof MessageBusInterface);
        $bus->dispatch($event, [TenantStamp::of($organizer)]);
    }

    /**
     * Delivers a Pairrot event to the webhook receiver (without HTTP: the signature has its own tests), like Pairrot would.
     *
     * @param array<string, mixed> $event
     *
     * @return 'accepted'|'duplicate'
     */
    protected function deliverWebhook(array $event): string
    {
        $result = $this->commandBus()->dispatch(new ReceivePairrotWebhook(json_encode($event, \JSON_THROW_ON_ERROR)));
        \assert('accepted' === $result || 'duplicate' === $result);

        return $result;
    }

    /**
     * @return array{id: string, pairrotId: string, state: string, status: string, creation: string, attempt: int, purpose: string, amount: int, paid: int}
     */
    protected function requestRow(OrderId $order, int $attempt = 1): array
    {
        $row = $this->dbRow(
            'SELECT id, pairrot_id, status, creation_state, attempt, purpose, amount_minor, known_paid_minor FROM payments.payment_request WHERE order_id = ? AND attempt = ?',
            [$order->toString(), $attempt],
        );

        return [
            'id' => self::t($row['id']),
            'pairrotId' => self::t($row['pairrot_id']),
            'state' => self::t($row['creation_state']),
            'status' => self::t($row['status']),
            'creation' => self::t($row['creation_state']),
            'attempt' => self::n($row['attempt']),
            'purpose' => self::t($row['purpose']),
            'amount' => self::n($row['amount_minor']),
            'paid' => self::n($row['known_paid_minor']),
        ];
    }

    protected function financeState(OrderId $order): string
    {
        return $this->dbString('SELECT state FROM payments.order_finance WHERE order_id = ?', [$order->toString()]);
    }

    protected function enterTenant(OrganizerId $organizer): void
    {
        $this->service(TenantContext::class)->enter($organizer);
        $this->service(TenantFilterSynchronizer::class)->sync();
    }

    /**
     * Dispatches a command on behalf of the organizer (the worker does the same with the TenantStamp of the message).
     */
    protected function dispatchAs(OrganizerId $organizer, object $command): mixed
    {
        return $this->commandBus()->dispatch($command, TenantStamp::of($organizer));
    }

    protected static function n(mixed $v): int
    {
        return \is_int($v) || \is_string($v) ? (int) $v : 0;
    }

    protected static function t(mixed $v): string
    {
        return \is_scalar($v) ? (string) $v : '';
    }

    protected function entityManager(): EntityManagerInterface
    {
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        \assert($em instanceof EntityManagerInterface);

        return $em;
    }
}
