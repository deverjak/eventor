<?php

declare(strict_types=1);

namespace Eventor\Attention\Domain;

use Eventor\Shared\Domain\Id\AttentionItemId;
use Eventor\Shared\Domain\Id\OrganizerId;

interface AttentionItemRepository
{
    public function add(AttentionItem $item): void;

    /** The item of this organizer, or null (also for another organizer's item). */
    public function findOfOrganizer(AttentionItemId $id, OrganizerId $organizer): ?AttentionItem;

    /** The open or acknowledged item of this cause, if any (at most one exists). */
    /**
     * Deduplication is per tenant: the same cause at two organizers opens two items. Platform-wide items have no
     * organizer and deduplicate among themselves.
     */
    public function findUnresolvedByDedupeKey(?OrganizerId $organizer, string $dedupeKey): ?AttentionItem;
}
