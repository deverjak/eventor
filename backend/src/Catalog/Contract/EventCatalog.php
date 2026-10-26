<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract;

use Eventor\Catalog\Contract\Exception\EventNotFound;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Read model of the event catalogue for other modules (chapter 02, "Registration -> Catalog").
 *
 * `registrationSnapshot()` is what Registration prices, validates and reserves against: published, immutable per
 * form version and price list version, never the live draft. Implementations may cache it.
 */
interface EventCatalog
{
    /**
     * Resolves the slug of a public URL to a published event.
     *
     * @throws EventNotFound when the slug is unknown, the event is a draft or belongs to another organizer
     */
    public function resolvePublishedSlug(OrganizerId $organizer, string $eventSlug): EventId;

    /**
     * Resolves the slug of a public archive URL (POST-07) to a completed or archived event.
     *
     * @throws EventNotFound when the slug is unknown, belongs to another organizer or the event is not finished
     */
    public function resolveArchivedSlug(OrganizerId $organizer, string $eventSlug): EventId;

    /**
     * @throws EventNotFound when the event is unknown or has not been published
     */
    public function registrationSnapshot(OrganizerId $organizer, EventId $event): PublishedEventSnapshot;

    /**
     * Display data for messages, check-in and reports.
     *
     * @throws EventNotFound
     */
    public function summary(OrganizerId $organizer, EventId $event): EventSummary;

    /**
     * @param list<EventId> $events
     *
     * @return array<string, EventSummary> keyed by event id (string); unknown events are absent
     */
    public function summaries(OrganizerId $organizer, array $events): array;
}
