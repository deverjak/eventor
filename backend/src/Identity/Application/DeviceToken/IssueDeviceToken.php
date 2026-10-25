<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\DeviceToken;

use Eventor\Shared\Domain\Id\CheckInListId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\UserAccountId;

/**
 * Issues a credential for a check-in device, optionally bound to one check-in list (CHK-01).
 */
final readonly class IssueDeviceToken
{
    public function __construct(
        public OrganizerId $organizerId,
        public string $label,
        public ?CheckInListId $checkInListId,
        public ?\DateTimeImmutable $expiresAt,
        public UserAccountId $createdBy,
    ) {}
}
