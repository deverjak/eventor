<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\DeviceToken;

use Eventor\Shared\Domain\Id\OrganizerId;

interface DeviceTokenRepository
{
    public function add(DeviceToken $token): void;

    /**
     * Needs an active tenant (DeviceToken is tenant-scoped).
     */
    public function find(DeviceTokenId $id, OrganizerId $organizer): ?DeviceToken;
}
