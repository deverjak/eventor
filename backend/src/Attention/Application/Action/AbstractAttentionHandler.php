<?php

declare(strict_types=1);

namespace Eventor\Attention\Application\Action;

use Eventor\Attention\Application\Transition\AcknowledgeAttentionItem;
use Eventor\Attention\Application\Transition\DismissAttentionItem;
use Eventor\Attention\Application\Transition\ResolveAttentionItem;
use Eventor\Attention\Contract\AttentionItemView;
use Eventor\Attention\Contract\AttentionState;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Every item can be acknowledged, resolved with a note or dismissed; subclasses add the links to the remedies
 * owned by other modules (`remedies()`).
 */
abstract class AbstractAttentionHandler implements AttentionHandler
{
    public function actions(AttentionItemView $item): array
    {
        if (!$item->state->isUnresolved()) {
            return [];
        }

        $actions = $this->remedies($item);
        if (AttentionState::Open === $item->state) {
            $actions[] = AttentionAction::command('acknowledge');
        }
        $actions[] = AttentionAction::command('resolve', noteAllowed: true);
        $actions[] = AttentionAction::command('dismiss', noteAllowed: true);

        return $actions;
    }

    public function commandFor(AttentionItemView $item, OrganizerId $organizer, string $action, ?string $note): ?object
    {
        return match ($action) {
            'acknowledge' => new AcknowledgeAttentionItem($item->id, $organizer),
            'resolve' => new ResolveAttentionItem($item->id, $organizer, $note),
            'dismiss' => new DismissAttentionItem($item->id, $organizer, $note),
            default => null,
        };
    }

    /**
     * @return list<AttentionAction> links to the module that owns the cause
     */
    protected function remedies(AttentionItemView $item): array
    {
        return [];
    }

    protected static function payloadString(AttentionItemView $item, string $key): ?string
    {
        $value = $item->payload[$key] ?? null;

        return \is_string($value) && '' !== $value ? $value : null;
    }
}
