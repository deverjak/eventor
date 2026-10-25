<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\Event;

use Eventor\Identity\Domain\DeviceToken\DeviceTokenId;
use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\CheckInListId;
use Eventor\Shared\Domain\Id\OrganizerId;

final readonly class DeviceTokenIssued implements Auditable
{
    public function __construct(
        public DeviceTokenId $deviceTokenId,
        public OrganizerId $organizerId,
        public ?CheckInListId $checkInListId,
        public string $label,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange(
            entityType: 'device_token',
            entityId: $this->deviceTokenId->toString(),
            action: 'device_token.issued',
            organizerId: $this->organizerId,
            after: ['checkInListId' => $this->checkInListId?->toString(), 'label' => $this->label],
        );
    }
}
