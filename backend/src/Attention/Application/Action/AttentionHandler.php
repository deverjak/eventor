<?php

declare(strict_types=1);

namespace Eventor\Attention\Application\Action;

use Eventor\Attention\Contract\AttentionItemView;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Type-specific behaviour of the queue (chapter 09, "AttentionHandler"): which actions an item offers and which
 * command executes a `command` action. Actions never touch another module's data: they either delegate through
 * that module's Contract or point to it with a `link`.
 */
interface AttentionHandler
{
    /**
     * @return list<AttentionAction> empty for a closed item
     */
    public function actions(AttentionItemView $item): array;

    /**
     * The command that executes a `command` action, or null when the key is not one of them.
     */
    public function commandFor(AttentionItemView $item, OrganizerId $organizer, string $action, ?string $note): ?object;
}
