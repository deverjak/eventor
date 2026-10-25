<?php

declare(strict_types=1);

namespace Eventor\Identity\Contract;

use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Sender data of an organizer: `From:` name and `Reply-To:` of its messages (COM-05).
 */
final readonly class OrganizerContact
{
    public function __construct(
        public OrganizerId $organizerId,
        public string $slug,
        public string $name,
        public string $contactEmail,
    ) {}
}
