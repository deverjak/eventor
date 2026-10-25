<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\PaymentRequest;

use Eventor\Payments\Domain\Request\PaymentRequestNotFound;
use Eventor\Payments\Domain\Request\PaymentRequestRepository;
use Eventor\Shared\Domain\Id\PaymentRequestId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RetryPaymentRequestHandler
{
    public function __construct(private PaymentRequestRepository $requests) {}

    /**
     * @return array{paymentRequestId: string}
     */
    public function __invoke(RetryPaymentRequest $command): array
    {
        $request = $this->requests->lock(PaymentRequestId::fromString($command->paymentRequestId)) ?? throw PaymentRequestNotFound::forId($command->paymentRequestId);
        $request->retry();

        return ['paymentRequestId' => $request->id()->toString()];
    }
}
