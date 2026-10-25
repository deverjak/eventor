<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\DeviceToken;

use Eventor\Shared\Domain\Id\OrganizerId;

final readonly class RevokeDeviceToken
{
    public function __construct(
        public OrganizerId $organizerId,
        /** The token id (uuid) from the device token list. */
        public string $deviceTokenId,
    ) {}
}
