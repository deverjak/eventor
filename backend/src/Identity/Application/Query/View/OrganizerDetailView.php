<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\Query\View;

/**
 * Response of `GET /api/admin/org/{orgSlug}`.
 */
final readonly class OrganizerDetailView
{
    public function __construct(
        public string $id,
        public string $slug,
        public string $name,
        public \DateTimeImmutable $createdAt,
        public int $memberCount,
        public string $yourRole,
    ) {}
}
