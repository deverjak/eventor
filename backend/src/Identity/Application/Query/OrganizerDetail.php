<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\Query;

/**
 * Response of `GET /api/admin/org/{orgSlug}`.
 */
final readonly class OrganizerDetail
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
