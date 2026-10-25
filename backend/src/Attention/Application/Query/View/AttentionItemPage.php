<?php

declare(strict_types=1);

namespace Eventor\Attention\Application\Query\View;

use Eventor\Attention\Contract\AttentionItemView;

/**
 * One page of the queue, newest first; `total` counts all items matching the filter.
 */
final readonly class AttentionItemPage
{
    /**
     * @param list<AttentionItemView> $items
     */
    public function __construct(
        public array $items,
        public int $total,
    ) {}
}
