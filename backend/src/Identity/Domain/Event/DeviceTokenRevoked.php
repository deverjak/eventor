<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\Event;

use Eventor\Identity\Domain\DeviceToken\DeviceTokenId;
use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\OrganizerId;

final readonly class DeviceTokenRevoked implements Auditable
{
    public function __construct(
        public DeviceTokenId $deviceTokenId,
        public OrganizerId $organizerId,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange(
            entityType: 'device_token',
            entityId: $this->deviceTokenId->toString(),
            action: 'device_token.revoked',
            organizerId: $this->organizerId,
        );
    }
}
