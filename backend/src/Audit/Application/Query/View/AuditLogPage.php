<?php

declare(strict_types=1);

namespace Eventor\Audit\Application\Query\View;

final readonly class AuditLogPage
{
    /**
     * @param list<AuditLogEntryView> $items newest first
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {}

    /**
     * @return array{items: list<array<string, mixed>>, total: int, page: int, perPage: int}
     */
    public function toArray(): array
    {
        return [
            'items' => array_map(static fn(AuditLogEntryView $entry): array => $entry->toArray(), $this->items),
            'total' => $this->total,
            'page' => $this->page,
            'perPage' => $this->perPage,
        ];
    }
}
