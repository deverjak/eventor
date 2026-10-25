<?php

declare(strict_types=1);

namespace Eventor\Payments\UserInterface\EventHandler;

use Eventor\Payments\Application\PaymentRequest\PaymentRequestIssuer;
use Eventor\Payments\Application\PaymentRequest\RegisterPaymentRequest;
use Eventor\Payments\Application\Port\OrganizerPaymentPolicy;
use Eventor\Payments\Contract\PaymentRequestPurpose;
use Eventor\Registration\Contract\Event\OrderPlaced;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Domain\Id\PaymentRequestId;
use Eventor\Shared\Domain\Payment\PaymentMethod;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * `registration.order_placed` -> `payments.create_payment_request` (chapter 04, path 3; PAY-02, PAY-03, S08).
 *
 * Only bank transfers with something to pay. The mirror row (`attempt = 1`, state PENDING) is written first, in its own
 * transaction; then Pairrot is called outside any transaction. A Pairrot outage throws, the outbox retries with
 * backoff (1 m, 5 m, 30 m, 2 h, 12 h) and the order and its participations are never touched. The retry replays the
 * same `external_id`, so a request that Pairrot created but whose answer was lost is not duplicated.
 */
#[AsMessageHandler(fromTransport: 'payments_events')]
final readonly class CreatePaymentRequestOnOrderPlaced
{
    public function __construct(
        private CommandBus $commands,
        private PaymentRequestIssuer $issuer,
        private OrganizerPaymentPolicy $policy,
        private ClockInterface $clock,
    ) {}

    public function __invoke(OrderPlaced $event): void
    {
        if (PaymentMethod::BankTransfer !== $event->paymentMethod || !$event->amountDue->isPositive()) {
            return;
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $dueAt = $event->holdExpiresAt ?? $now->modify('+'.$this->policy->defaultDueDays($event->organizerId).' days');

        /** @var array{paymentRequestId: string, creationState: string, attempt: int} $registered */
        $registered = $this->commands->dispatch(new RegisterPaymentRequest(
            $event->orderId->toString(),
            1,
            PaymentRequestPurpose::Initial->value,
            $event->amountDue->minor,
            self::messageFor($event->orderNumber),
            $dueAt->format(\DATE_ATOM),
        ));

        if ('PENDING' !== $registered['creationState']) {
            return; // a redelivery after the request was issued (or failed for good)
        }

        $this->issuer->issue(PaymentRequestId::fromString($registered['paymentRequestId']));
    }

    /**
     * The message for the payee: the order number, at most 60 characters, without `*` and `%` (Pairrot refuses them).
     */
    public static function messageFor(string $orderNumber): string
    {
        return mb_substr(str_replace(['*', '%'], ' ', $orderNumber), 0, 60);
    }
}
