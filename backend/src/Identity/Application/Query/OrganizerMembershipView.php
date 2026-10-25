<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\Query;

final readonly class OrganizerMembershipView
{
    public function __construct(
        public string $organizerId,
        public string $slug,
        public string $name,
        public string $role,
    ) {}
}
