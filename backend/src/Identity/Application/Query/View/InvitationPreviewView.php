<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\Query\View;

/**
 * What the accept page shows before the person commits.
 */
final readonly class InvitationPreviewView
{
    public function __construct(
        public string $organizerName,
        public string $organizerSlug,
        public string $email,
        public string $role,
        public bool $accountExists,
        public string $expiresAt,
    ) {}
}
