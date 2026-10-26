<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Query\View;

use Eventor\Shared\Domain\Finance\FinancialState;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Money;
use Eventor\Shared\Domain\Payment\PaymentMethod;

/**
 * An order in the admin: contact, method, money and the participations with their states.
 */
final readonly class AdminOrderView
{
    /**
     * @param list<ParticipationRowView> $participations
     */
    public function __construct(
        public OrderId $id,
        public string $number,
        public EventId $eventId,
        public string $ordererName,
        public string $ordererEmail,
        public ?string $ordererPhone,
        public PaymentMethod $paymentMethod,
        public string $origin,
        public Money $amountDue,
        public ?FinancialState $financialState,
        public Money $received,
        public \DateTimeImmutable $placedAt,
        public array $participations,
    ) {}
}
