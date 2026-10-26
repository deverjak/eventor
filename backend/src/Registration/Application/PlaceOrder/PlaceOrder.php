<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\PlaceOrder;

use Eventor\Registration\Domain\Order\OrderOrigin;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Payment\PaymentMethod;

/**
 * REG-01..03, REG-06: one order with N persons and M participations, placed in one transaction (chapter 04, path 2).
 */
final readonly class PlaceOrder
{
    /**
     * @param list<OrderItemInput> $items
     * @param list<PersonInput>    $persons
     * @param array<string, mixed> $orderAnswers raw answers of the order-scoped fields (the contact of the orderer)
     * @param list<DecisionInput>  $decisions
     */
    public function __construct(
        public OrganizerId $organizerId,
        public EventId $eventId,
        public array $items,
        public array $persons,
        public array $orderAnswers,
        public array $decisions,
        public ?PaymentMethod $paymentMethod,
        public string $locale = 'cs',
        public OrderOrigin $origin = OrderOrigin::Public,
    ) {}
}
