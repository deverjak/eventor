<?php

declare(strict_types=1);

namespace Eventor\Attention\Application\Open;

use Eventor\Attention\Domain\AttentionItem;
use Eventor\Attention\Domain\AttentionItemRepository;
use Eventor\Shared\Domain\Id\AttentionItemId;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('A repeated cause (subscribers are at-least-once) opens nothing; a new item records AttentionItemOpened.')]
final readonly class OpenAttentionItemHandler
{
    public function __construct(
        private AttentionItemRepository $items,
        private ClockInterface $clock,
    ) {}

    /**
     * @return string|null the id of the new item, null when the cause already has an unresolved one
     */
    public function __invoke(OpenAttentionItem $command): ?string
    {
        $key = OpenAttentionItem::dedupeKey($command->type, $command->subjectType, $command->subjectId);
        if (null !== $this->items->findUnresolvedByDedupeKey($key)) {
            return null;
        }

        $item = AttentionItem::open(
            AttentionItemId::generate(),
            $command->organizerId,
            $command->type,
            $key,
            $command->subjectType,
            $command->subjectId,
            $command->eventId,
            $command->payload,
            \DateTimeImmutable::createFromInterface($this->clock->now()),
        );
        $this->items->add($item);

        return $item->id()->toString();
    }
}
