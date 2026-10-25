<?php

declare(strict_types=1);

namespace Eventor\Attention\Application\Action;

use Eventor\Attention\Application\Query\AttentionItemQuery;
use Eventor\Attention\Contract\AttentionItemView;
use Eventor\Attention\Contract\Exception\AttentionActionNotAvailable;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Domain\Id\AttentionItemId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Runs one of the actions an item offers: the type's handler names the command, the command bus executes it
 * (one transaction, audit, domain events). Link actions are not executed here; they belong to another module.
 */
final readonly class AttentionActionRunner
{
    public function __construct(
        private AttentionItemQuery $items,
        private AttentionHandlers $handlers,
        private CommandBus $commands,
    ) {}

    public function __invoke(OrganizerId $organizer, AttentionItemId $id, string $action, ?string $note): AttentionItemView
    {
        $item = $this->items->get($organizer, $id);
        $handler = $this->handlers->for($item->type);

        $offered = array_filter($handler->actions($item), static fn(AttentionAction $a): bool => $a->key === $action && AttentionAction::KIND_COMMAND === $a->kind);
        $command = [] === $offered ? null : $handler->commandFor($item, $organizer, $action, $note);
        if (null === $command) {
            throw AttentionActionNotAvailable::for($action, $item->state->value);
        }

        $this->commands->dispatch($command);

        return $this->items->get($organizer, $id);
    }
}
