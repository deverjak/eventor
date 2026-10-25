<?php

declare(strict_types=1);

namespace Eventor\Tests\Fixtures\Http;

use Eventor\Identity\Contract\Attribute\CurrentOrganizer;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Test-only admin endpoints that need a stricter permission than organizer.view, to prove 403 (and, together with the
 * real organizer endpoint, the 401 -> 404 -> 403 order). Registered in the test environment only.
 */
#[AsController]
final class ProbeTeamController
{
    #[Route('/api/admin/org/{orgSlug}/_probe/team', name: 'probe_team_read', methods: ['GET'])]
    #[RequiresPermission(Permission::TeamManage)]
    public function read(#[CurrentOrganizer] OrganizerView $organizer): JsonResponse
    {
        return new JsonResponse(['organizer' => $organizer->slug, 'ok' => true]);
    }

    #[Route('/api/admin/org/{orgSlug}/_probe/team', name: 'probe_team_write', methods: ['POST'])]
    #[RequiresPermission(Permission::TeamManage)]
    public function write(#[CurrentOrganizer] OrganizerView $organizer): JsonResponse
    {
        return new JsonResponse(['organizer' => $organizer->slug, 'changed' => true]);
    }
}
