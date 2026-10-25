<?php

declare(strict_types=1);

namespace Eventor\Tests\Fixtures\Controller;

use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/** Fixture for PermissionMatrixPassTest (not registered as a service). */
#[AsController]
final class UnguardedAdminController
{
    #[Route('/api/admin/org/{orgSlug}/unguarded', methods: ['GET'])]
    public function __invoke(): void {}
}
