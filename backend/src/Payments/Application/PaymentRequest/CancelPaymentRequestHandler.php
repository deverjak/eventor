<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\PaymentRequest;

use Eventor\Payments\Domain\Request\PaymentRequestNotFound;
use Eventor\Payments\Domain\Request\PaymentRequestRepository;
use Eventor\Shared\Domain\Id\PaymentRequestId;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('Cancelling a request that is already cancelled changes nothing; the first call recorded PaymentRequestCancelled.')]
final readonly class CancelPaymentRequestHandler
{
    public function __construct(
        private PaymentRequestRepository $requests,
        private ClockInterface $clock,
    ) {}

    public function __invoke(CancelPaymentRequest $command): void
    {
        $request = $this->requests->lock(PaymentRequestId::fromString($command->paymentRequestId)) ?? throw PaymentRequestNotFound::forId($command->paymentRequestId);
        $request->cancel($command->reason, \DateTimeImmutable::createFromInterface($this->clock->now()));
    }
}
