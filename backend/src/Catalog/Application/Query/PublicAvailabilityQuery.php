<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query;

use Eventor\Catalog\Application\Query\View\AvailabilityStatusView;
use Eventor\Catalog\Application\Query\View\PublicAvailabilityView;
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
        private EventCatalogQuery $catalog,
        private ?AvailabilityReader $availability = null,
    ) {}

    /**
     * @throws EventNotFound
     */
    public function forSlug(OrganizerId $organizer, string $eventSlug): PublicAvailabilityView
    {
        $event = $this->catalog->resolvePublishedSlug($organizer, $eventSlug);
        if (null === $this->availability) {
            return new PublicAvailabilityView($event->toString(), false, null, [], []);
        }

        try {
            $buckets = $this->availability->forEvent($organizer, $event)->buckets;
        } catch (CapacityBucketNotFound) {
            return new PublicAvailabilityView($event->toString(), false, null, [], []);
        }

        $eventView = null;
        $activities = [];
        $variants = [];
        foreach ($buckets as $bucket) {
            $view = AvailabilityStatusView::of($bucket->available);
            match ($bucket->scope) {
                CapacityScope::Event => $eventView = $view,
                CapacityScope::Activity => $activities[$bucket->scopeId] = $view,
                CapacityScope::Variant => $variants[$bucket->scopeId] = $view,
                CapacityScope::Wave => null,
            };
        }

        return new PublicAvailabilityView($event->toString(), true, $eventView, $activities, $variants);
    }
}
