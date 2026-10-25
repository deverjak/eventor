<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\Query\View;

/**
 * A member of the team in `GET /api/admin/org/{orgSlug}/members`.
 */
final readonly class TeamMemberView
{
    /**
     * @param list<string> $eventScope event ids the member is restricted to; empty = all events
     */
    public function __construct(
        public string $membershipId,
        public string $userAccountId,
        public string $email,
        public string $displayName,
        public string $role,
        public array $eventScope,
        public string $joinedAt,
    ) {}
}
