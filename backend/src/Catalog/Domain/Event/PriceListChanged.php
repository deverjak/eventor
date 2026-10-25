<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Event;

use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Domain event: a price or the set of priced variants changed, so the price list has a new version. Registration
 * prices every order against one `priceListVersion` of the snapshot.
 */
final readonly class PriceListChanged implements Auditable
{
    public function __construct(
        public OrganizerId $organizerId,
        public EventId $eventId,
        public int $version,
        public bool $live,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange('event', $this->eventId->toString(), 'event.price_list_changed', $this->organizerId, after: ['priceListVersion' => $this->version]);
    }
}
