<?php

declare(strict_types=1);

namespace Eventor\Audit\Contract;

use Eventor\Shared\Domain\Id\OrganizerId;

interface AuditTrail
{
    /**
     * History of one entity of the organizer, oldest first.
     *
     * @return list<AuditEntryView>
     */
    public function forEntity(OrganizerId $organizer, string $entityType, string $entityId): array;
}
