<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query;

use Eventor\Catalog\Contract\Exception\EventNotFound;
use Eventor\Registration\Contract\AvailabilityReader;
use Eventor\Registration\Contract\Exception\CapacityBucketNotFound;
use Eventor\Shared\Domain\Capacity\CapacityScope;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * The free places of a published event by event, activity and variant (PUB-03), without the rest of the page, so the
 * frontend can refresh the numbers cheaply. The numbers come from Registration's `AvailabilityReader`.
 */
final readonly class PublicAvailabilityQuery
{
    public function __construct(
        private CatalogEventCatalog $catalog,
        private ?AvailabilityReader $availability = null,
    ) {}

    /**
     * @return array{eventId: string, shown: bool, event: array{status: string, available: int|null}|null, activities: array<string, array{status: string, available: int|null}>, variants: array<string, array{status: string, available: int|null}>}
     *
     * @throws EventNotFound
     */
    public function forSlug(OrganizerId $organizer, string $eventSlug): array
    {
        $event = $this->catalog->resolvePublishedSlug($organizer, $eventSlug);
        $result = ['eventId' => $event->toString(), 'shown' => false, 'event' => null, 'activities' => [], 'variants' => []];
        if (null === $this->availability) {
            return $result;
        }

        try {
            $buckets = $this->availability->forEvent($organizer, $event)->buckets;
        } catch (CapacityBucketNotFound) {
            return $result;
        }
        $result['shown'] = true;
        foreach ($buckets as $bucket) {
            $view = AvailabilityView::describe($bucket->available);
            match ($bucket->scope) {
                CapacityScope::Event => $result['event'] = $view,
                CapacityScope::Activity => $result['activities'][$bucket->scopeId] = $view,
                CapacityScope::Variant => $result['variants'][$bucket->scopeId] = $view,
                CapacityScope::Wave => null,
            };
        }

        return $result;
    }
}
