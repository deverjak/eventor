<?php

declare(strict_types=1);

namespace Eventor\Attention\Application\Open;

use Eventor\Attention\Application\Close\CloseAttentionItem;
use Eventor\Registration\Contract\Exception\OrderNotFound;
use Eventor\Registration\Contract\OrderQuery;
use Eventor\Shared\Application\Attention\AttentionType;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * What the subscribers of integration events call: open an item for a cause, or close the item whose cause went
 * away. Both go through the command bus (one transaction, audit). The event of an order is looked up through
 * Registration's `OrderQuery` when that facade is available, so the queue can be filtered by event (ADM-03).
 */
final readonly class AttentionIntake
{
    public function __construct(
        private CommandBus $commands,
        private ?OrderQuery $orders = null,
    ) {}

    /**
     * @param array<string, scalar|null> $payload
     */
    public function open(AttentionType $type, ?OrganizerId $organizer, string $subjectType, string $subjectId, array $payload = [], ?EventId $event = null): void
    {
        $this->commands->dispatch(new OpenAttentionItem($type, $organizer, $subjectType, $subjectId, $event, $payload));
    }

    /**
     * @param array<string, scalar|null> $payload
     */
    public function openForOrder(AttentionType $type, OrganizerId $organizer, OrderId $order, string $subjectType, string $subjectId, array $payload = []): void
    {
        $this->open($type, $organizer, $subjectType, $subjectId, ['orderId' => $order->toString()] + $payload, $this->eventOf($organizer, $order));
    }

    public function close(AttentionType $type, string $subjectType, string $subjectId): void
    {
        $this->commands->dispatch(new CloseAttentionItem($type, $subjectType, $subjectId));
    }

    private function eventOf(OrganizerId $organizer, OrderId $order): ?EventId
    {
        try {
            return $this->orders?->get($organizer, $order)->eventId;
        } catch (OrderNotFound) {
            return null;
        }
    }
}
