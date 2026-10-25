<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\Organizer;

use Eventor\Shared\Domain\Id\OrganizerId;

interface OrganizerRepository
{
    public function findBySlug(string $slug): ?Organizer;

    public function findById(OrganizerId $id): ?Organizer;

    public function add(Organizer $organizer): void;
}
