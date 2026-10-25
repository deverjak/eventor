<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\PaymentRequest;

use Eventor\Payments\Domain\Request\PaymentRequestRepository;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('When no request is pending nothing is failed; a failure records PaymentRequestCreationFailed.')]
final readonly class FailPendingPaymentRequestsHandler
{
    public function __construct(
        private PaymentRequestRepository $requests,
        private ClockInterface $clock,
    ) {}

    public function __invoke(FailPendingPaymentRequests $command): void
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        foreach ($this->requests->forOrder(OrderId::fromString($command->orderId)) as $request) {
            $request->giveUp($command->error, $now);
        }
    }
}
