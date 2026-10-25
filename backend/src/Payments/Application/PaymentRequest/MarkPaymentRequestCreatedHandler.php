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
#[NoDomainEvent('Confirming an already issued request changes nothing; the first confirmation recorded PaymentRequestCreated.')]
final readonly class MarkPaymentRequestCreatedHandler
{
    public function __construct(
        private PaymentRequestRepository $requests,
        private ClockInterface $clock,
    ) {}

    public function __invoke(MarkPaymentRequestCreated $command): void
    {
        $request = $this->requests->lock(PaymentRequestId::fromString($command->paymentRequestId)) ?? throw PaymentRequestNotFound::forId($command->paymentRequestId);
        $request->markCreated(
            $command->pairrotId,
            $command->variableSymbol,
            $command->accountNumber,
            $command->iban,
            $command->spayd,
            \DateTimeImmutable::createFromInterface($this->clock->now()),
        );
    }
}
