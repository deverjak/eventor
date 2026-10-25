<?php

declare(strict_types=1);

namespace Eventor\Identity\Contract\Attribute;

use Eventor\Identity\Contract\Permission;

/**
 * Declares the permission a controller action needs. Every `/api/admin` action must carry it (PermissionMatrixPass
 * fails the container build otherwise). Evaluated after argument resolution, so the subject is the resolved
 * `#[CurrentOrganizer]` organizer unless `subject` names another argument; 401 -> 404 -> 403 order is preserved.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final readonly class RequiresPermission
{
    public function __construct(
        public Permission $permission,
        /** Name of the controller argument to vote on; default: the organizer argument. */
        public ?string $subject = null,
    ) {}
}
