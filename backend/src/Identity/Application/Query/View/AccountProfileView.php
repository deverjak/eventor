<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\Query\View;

/**
 * Response of `GET /api/auth/me` and of a successful login: who is signed in and where they are a member.
 */
final readonly class AccountProfileView
{
    /**
     * @param list<OrganizerMembershipView> $memberships
     */
    public function __construct(
        public string $id,
        public string $email,
        public string $displayName,
        public array $memberships,
    ) {}
}
