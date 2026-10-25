<?php

declare(strict_types=1);

namespace Eventor\Attention\Application\Query;

use Eventor\Attention\Contract\AttentionQueue;
use Eventor\Attention\Contract\AttentionSummary;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(AttentionQueue::class)]
final readonly class AttentionQueueQuery implements AttentionQueue
{
    public function __construct(private AttentionItemQuery $items) {}

    public function summary(OrganizerId $organizer, ?EventId $event = null): AttentionSummary
    {
        $byType = $this->items->unresolvedByType($organizer, $event);

        return new AttentionSummary(array_sum($byType), $byType);
    }

    public function forSubject(OrganizerId $organizer, string $subjectType, string $subjectId): array
    {
        return $this->items->forSubject($organizer, $subjectType, $subjectId);
    }
}
