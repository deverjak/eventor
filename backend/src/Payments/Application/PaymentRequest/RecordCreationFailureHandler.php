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
#[NoDomainEvent('A failure reported for a request that is no longer pending changes nothing.')]
final readonly class RecordCreationFailureHandler
{
    public function __construct(
        private PaymentRequestRepository $requests,
        private ClockInterface $clock,
    ) {}

    public function __invoke(RecordCreationFailure $command): void
    {
        $request = $this->requests->lock(PaymentRequestId::fromString($command->paymentRequestId)) ?? throw PaymentRequestNotFound::forId($command->paymentRequestId);
        $request->recordFailedAttempt($command->error);
        if ($command->final) {
            $request->giveUp($command->error, \DateTimeImmutable::createFromInterface($this->clock->now()));
        }
    }
}
