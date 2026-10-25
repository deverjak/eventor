<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\Query\View;

/**
 * An open invitation in `GET /api/admin/org/{orgSlug}/invitations`.
 */
final readonly class PendingInvitationView
{
    /**
     * @param list<string> $eventScope
     */
    public function __construct(
        public string $invitationId,
        public string $email,
        public string $role,
        public array $eventScope,
        public string $invitedAt,
        public string $expiresAt,
    ) {}
}
