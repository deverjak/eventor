<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query\View;

/**
 * Response of `GET /events`.
 */
final readonly class EventListView
{
    /**
     * @param list<EventListItemView> $items
     */
    public function __construct(public array $items) {}
}
