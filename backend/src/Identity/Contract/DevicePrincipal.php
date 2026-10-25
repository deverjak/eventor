<?php

declare(strict_types=1);

namespace Eventor\Identity\Contract;

use Eventor\Shared\Domain\Id\CheckInListId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * The authenticated principal of a check-in device (`Authorization: Bearer dev1_...` on `/api/check-in`). The
 * CheckIn module reads it from the security token (`$security->getUser() instanceof DevicePrincipal`) to learn which
 * organizer the device belongs to and which check-in list it may work on (null = every list of the organizer).
 */
interface DevicePrincipal
{
    public function deviceTokenId(): string;

    public function organizerId(): OrganizerId;

    public function checkInListId(): ?CheckInListId;

    public function label(): string;
}
