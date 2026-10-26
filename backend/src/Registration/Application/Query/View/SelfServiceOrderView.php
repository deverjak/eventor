<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Query\View;

use Eventor\Shared\Domain\Finance\FinancialState;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Money;
use Eventor\Shared\Domain\Payment\PaymentMethod;

/**
 * What the participant sees behind the self-service link (REG-08): the order, who is in it, what is paid and how to pay,
 * the QR code of every participation and what each one still owes. Personal data of the participant's own order only.
 */
final readonly class SelfServiceOrderView
{
    /**
     * @param list<SelfServiceParticipationView> $participations
     * @param list<PaymentInstructionView>       $paymentInstructions
     */
    public function __construct(
        public OrderId $orderId,
        public string $number,
        public EventId $eventId,
        public string $eventName,
        public ?\DateTimeImmutable $eventStartsAt,
        public string $ordererName,
        public string $ordererEmail,
        public PaymentMethod $paymentMethod,
        public Money $amountDue,
        public FinancialState $financialState,
        public Money $received,
        public Money $outstanding,
        public bool $paymentInstructionsPending,
        public array $paymentInstructions,
        public bool $cancellationAllowed,
        public ?\DateTimeImmutable $cancellationDeadline,
        public array $participations,
    ) {}
}
