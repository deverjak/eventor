<?php

declare(strict_types=1);

namespace Eventor\Identity\Contract;

use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Role x permission decisions for other modules' voters. The matrix and organizer policy exceptions stay inside Identity.
 */
interface AccessPolicy
{
    public function isAllowed(Permission $permission, OrganizerId $organizer, string $userIdentifier): bool;

    /**
     * Like `isAllowed()`, and additionally the membership must cover the event: a member restricted to some events
     * (`membership_event_scope`) is refused for any other event. Event-level voters of other modules call this.
     */
    public function isAllowedForEvent(Permission $permission, OrganizerId $organizer, EventId $event, string $userIdentifier): bool;

    /**
     * The role of the user in the organizer (`OWNER`, `EVENT_MANAGER`, `FINANCE`, `CHECK_IN`), null for non-members.
     */
    public function roleOf(OrganizerId $organizer, string $userIdentifier): ?string;

    /**
     * The events the membership is restricted to. An empty list means "all events of the organizer" (also for
     * non-members, who are refused by `isAllowed()` anyway). List queries apply it in SQL.
     *
     * @return list<EventId>
     */
    public function eventScope(OrganizerId $organizer, string $userIdentifier): array;
}
