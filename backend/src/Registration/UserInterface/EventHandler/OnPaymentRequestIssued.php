<?php

declare(strict_types=1);

namespace Eventor\Registration\UserInterface\EventHandler;

use Eventor\Payments\Contract\Event\PaymentRequestIssued;
use Eventor\Registration\Application\Hold\ExtendHoldIfLate;
use Eventor\Shared\Application\Audit\AuditContext;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Eventor\Shared\Domain\Audit\AuditActor;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * `payments.payment_request_issued`: prolong the reservation if the instructions came late.
 */
#[AsMessageHandler(fromTransport: 'registration_events')]
final readonly class OnPaymentRequestIssued
{
    public function __construct(
        private CommandBus $commands,
        private AuditContext $audit,
    ) {}

    public function __invoke(PaymentRequestIssued $event): void
    {
        $this->audit->as(AuditActor::system('registration.extend_hold_if_late'), fn(): mixed => $this->commands->dispatch(
            new ExtendHoldIfLate($event->organizerId, $event->orderId, $event->occurredAt),
            TenantStamp::of($event->organizerId),
        ));
    }
}
