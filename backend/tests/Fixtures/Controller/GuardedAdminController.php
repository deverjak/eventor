<?php

declare(strict_types=1);

namespace Eventor\Tests\Fixtures\Controller;

use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\Permission;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/** Fixture for PermissionMatrixPassTest (not registered as a service). */
#[AsController]
final class GuardedAdminController
{
    #[Route('/api/admin/org/{orgSlug}/guarded', methods: ['GET'])]
    #[RequiresPermission(Permission::TeamManage)]
    public function __invoke(): void {}
}
