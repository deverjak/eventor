<?php

declare(strict_types=1);

namespace Eventor\Attention\Contract;

use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Read side of the "needs attention" queue (ADM-03). Items are opened by Attention's own subscribers (and by the
 * kernel through `Shared\Application\Attention\AttentionRaiser`) and closed by reacting to the resulting domain
 * events, never by hand-ticking; other modules only read.
 */
interface AttentionQueue
{
    public function summary(OrganizerId $organizer, ?EventId $event = null): AttentionSummary;

    /**
     * Items about a subject (an order, a participation, a delivery, a transaction), newest first.
     *
     * @return list<AttentionItemView>
     */
    public function forSubject(OrganizerId $organizer, string $subjectType, string $subjectId): array;
}
