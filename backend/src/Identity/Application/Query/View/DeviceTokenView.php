<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\Query\View;

/**
 * A device token in the admin list; never contains the secret.
 */
final readonly class DeviceTokenView
{
    public function __construct(
        public string $id,
        public string $label,
        public ?string $checkInListId,
        public string $createdAt,
        public ?string $expiresAt,
        public ?string $revokedAt,
        public bool $active,
    ) {}
}
