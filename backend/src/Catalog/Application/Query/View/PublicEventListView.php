<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query\View;

/**
 * Response of the public event list.
 */
final readonly class PublicEventListView
{
    /**
     * @param list<PublicEventListItemView> $items
     */
    public function __construct(public array $items) {}
}
