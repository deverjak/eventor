<?php

declare(strict_types=1);

namespace Eventor\Attention\Application\Close;

use Eventor\Attention\Application\Open\OpenAttentionItem;
use Eventor\Attention\Domain\AttentionItem;
use Eventor\Attention\Domain\AttentionItemRepository;
use Eventor\Shared\Application\Audit\AuditContext;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('A redelivered event finds nothing to close; closing an item records AttentionItemClosed.')]
final readonly class CloseAttentionItemHandler
{
    public function __construct(
        private AttentionItemRepository $items,
        private WorkflowInterface $attentionItemStateMachine,
        private AuditContext $audit,
        private ClockInterface $clock,
    ) {}

    public function __invoke(CloseAttentionItem $command): bool
    {
        $item = $this->items->findUnresolvedByDedupeKey(OpenAttentionItem::dedupeKey($command->type, $command->subjectType, $command->subjectId));
        if (!$item instanceof AttentionItem) {
            return false;
        }

        $actor = $this->audit->actor();
        $item->resolve(
            $this->attentionItemStateMachine,
            $actor->id ?? $actor->label ?? 'system',
            AttentionItem::RESOLUTION_AUTO,
            null,
            \DateTimeImmutable::createFromInterface($this->clock->now()),
        );

        return true;
    }
}
