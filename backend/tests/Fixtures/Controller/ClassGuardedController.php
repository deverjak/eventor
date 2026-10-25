<?php

declare(strict_types=1);

namespace Eventor\Tests\Fixtures\Controller;

use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\Permission;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/** Fixture for PermissionMatrixPassTest: a class-level #[RequiresPermission] guards every action. */
#[AsController]
#[RequiresPermission(Permission::EventEdit)]
final class ClassGuardedController
{
    #[Route('/api/admin/org/{orgSlug}/events', methods: ['GET'])]
    public function list(): void {}

    #[Route('/api/admin/org/{orgSlug}/events/{id}', methods: ['GET'])]
    public function show(): void {}
}
