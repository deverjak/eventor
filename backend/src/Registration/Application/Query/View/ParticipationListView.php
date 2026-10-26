<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Query\View;

/**
 * A page of the participant list.
 */
final readonly class ParticipationListView
{
    /**
     * @param list<ParticipationRowView> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $limit,
    ) {}
}
