<?php

declare(strict_types=1);

namespace Eventor\Attention\Application\Transition;

use Eventor\Attention\Contract\Exception\AttentionItemNotFound;
use Eventor\Attention\Domain\AttentionItemRepository;
use Eventor\Shared\Application\Audit\AuditContext;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class DismissAttentionItemHandler
{
    public function __construct(
        private AttentionItemRepository $items,
        private WorkflowInterface $attentionItemStateMachine,
        private AuditContext $audit,
        private ClockInterface $clock,
    ) {}

    public function __invoke(DismissAttentionItem $command): void
    {
        $item = $this->items->findOfOrganizer($command->itemId, $command->organizerId) ?? throw AttentionItemNotFound::forId($command->itemId);
        $item->dismiss($this->attentionItemStateMachine, $this->actorName(), $command->note, $this->now());
    }

    private function actorName(): string
    {
        $actor = $this->audit->actor();

        return $actor->id ?? $actor->label ?? 'system';
    }

    private function now(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($this->clock->now());
    }
}
