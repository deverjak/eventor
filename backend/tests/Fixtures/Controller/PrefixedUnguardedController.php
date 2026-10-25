<?php

declare(strict_types=1);

namespace Eventor\Tests\Fixtures\Controller;

use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/** Fixture for PermissionMatrixPassTest: the admin prefix comes from the class-level #[Route]. */
#[AsController]
#[Route('/api/admin/org/{orgSlug}/things')]
final class PrefixedUnguardedController
{
    #[Route('', methods: ['GET'])]
    public function list(): void {}
}
