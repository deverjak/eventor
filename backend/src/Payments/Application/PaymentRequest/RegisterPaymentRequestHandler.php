<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\PaymentRequest;

use Eventor\Payments\Application\Port\PairrotAccounts;
use Eventor\Payments\Contract\PaymentRequestPurpose;
use Eventor\Payments\Domain\Request\PairrotNotConnected;
use Eventor\Payments\Domain\Request\PaymentRequest;
use Eventor\Payments\Domain\Request\PaymentRequestRepository;
use Eventor\Shared\Application\Tenant\TenantContext;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\PaymentRequestId;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Eventor\Shared\Domain\Money;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('A redelivered order event finds the request registered by the first delivery and registers nothing.')]
final readonly class RegisterPaymentRequestHandler
{
    public function __construct(
        private PaymentRequestRepository $requests,
        private PairrotAccounts $accounts,
        private TenantContext $tenant,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{paymentRequestId: string, creationState: string, attempt: int}
     */
    public function __invoke(RegisterPaymentRequest $command): array
    {
        $organizer = $this->tenant->require();
        $order = OrderId::fromString($command->orderId);

        $existing = $this->requests->findByOrderAndAttempt($order, $command->attempt);
        if (null !== $existing) {
            return ['paymentRequestId' => $existing->id()->toString(), 'creationState' => $existing->creationState()->value, 'attempt' => $existing->attempt()];
        }

        $account = $this->accounts->find($organizer);
        if (null === $account || null === $account->bankConnectionId) {
            throw PairrotNotConnected::create();
        }

        $request = PaymentRequest::register(
            PaymentRequestId::generate(),
            $organizer,
            $order,
            $command->attempt,
            PaymentRequestPurpose::from($command->purpose),
            Money::of($command->amountMinor),
            $command->message,
            new \DateTimeImmutable($command->dueAt),
            $account->subtenantId,
            $account->bankConnectionId,
            \DateTimeImmutable::createFromInterface($this->clock->now()),
        );
        $this->requests->add($request);

        return ['paymentRequestId' => $request->id()->toString(), 'creationState' => $request->creationState()->value, 'attempt' => $request->attempt()];
    }
}
