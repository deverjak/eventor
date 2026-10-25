<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract;

use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrderId;

/**
 * Everything a transactional message about an order needs, besides event and payment data from their owners.
 */
final readonly class RecipientContext
{
    /**
     * @param list<ParticipantRef> $participants
     */
    public function __construct(
        public OrderId $orderId,
        public string $orderNumber,
        public EventId $eventId,
        public RecipientContact $contact,
        public array $participants,
    ) {}
}
