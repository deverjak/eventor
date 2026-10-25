<?php

declare(strict_types=1);

namespace Eventor\Attention\Application\Presenter;

use Eventor\Attention\Application\Action\AttentionAction;
use Eventor\Attention\Application\Action\AttentionHandlers;
use Eventor\Attention\Contract\AttentionItemView;

/**
 * JSON shape of a queue item for the admin API; the detail adds the actions the handler of its type offers.
 */
final readonly class AttentionItemPresenter
{
    public function __construct(private AttentionHandlers $handlers) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(AttentionItemView $item): array
    {
        return [
            'id' => $item->id->toString(),
            'type' => $item->type->value,
            'state' => $item->state->value,
            'subjectType' => $item->subjectType,
            'subjectId' => $item->subjectId,
            'eventId' => $item->eventId?->toString(),
            'openedAt' => $item->openedAt->format(\DATE_ATOM),
            'acknowledgedAt' => $item->acknowledgedAt?->format(\DATE_ATOM),
            'resolvedAt' => $item->resolvedAt?->format(\DATE_ATOM),
            'resolution' => $item->resolution,
            'resolutionNote' => $item->resolutionNote,
            'payload' => $item->payload,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(AttentionItemView $item): array
    {
        return $this->summary($item) + [
            'actions' => array_map(
                static fn(AttentionAction $action): array => $action->toArray(),
                $this->handlers->for($item->type)->actions($item),
            ),
        ];
    }
}
