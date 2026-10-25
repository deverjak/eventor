<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\Organizer;

interface OrganizerRepository
{
    public function findBySlug(string $slug): ?Organizer;

    public function add(Organizer $organizer): void;
}
