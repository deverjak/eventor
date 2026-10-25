<?php

declare(strict_types=1);

namespace Eventor\Attention\Contract;

use Eventor\Shared\Application\Attention\AttentionType;
use Eventor\Shared\Domain\Id\AttentionItemId;
use Eventor\Shared\Domain\Id\EventId;

/**
 * One queue item. `payload` holds neutral context for the UI (ids, amounts), no personal data.
 */
final readonly class AttentionItemView
{
    /**
     * @param array<string, scalar|null> $payload
     */
    public function __construct(
        public AttentionItemId $id,
        public AttentionType $type,
        public AttentionState $state,
        public string $subjectType,
        public string $subjectId,
        public ?EventId $eventId,
        public \DateTimeImmutable $openedAt,
        public array $payload,
    ) {}
}
